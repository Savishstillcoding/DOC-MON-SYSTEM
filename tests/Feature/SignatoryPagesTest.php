<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentAction;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The signatory's For Review, History and Profile pages, and "Approve all".
 */
class SignatoryPagesTest extends TestCase
{
    use RefreshDatabase;

    private Organization $digits;

    private User $officer;

    private User $adviser;   // step 1

    private User $vpsd;      // step 2, final say

    protected function setUp(): void
    {
        parent::setUp();

        $this->digits = Organization::create(['code' => 'DIGITS', 'name' => 'DIGITS']);
        $this->adviser = $this->makeUser('signatory', 'EMP-1', ['first_name' => 'Ana', 'last_name' => 'Dela Cruz', 'position' => 'Adviser']);
        $this->vpsd = $this->makeUser('signatory', 'EMP-2', ['first_name' => 'Liza', 'last_name' => 'Santos']);
        $this->digits->signatories()->attach($this->adviser, ['step' => 1, 'title' => 'Adviser']);
        $this->digits->signatories()->attach($this->vpsd, ['step' => 2, 'title' => 'VPSD']);
        $this->officer = $this->makeUser('officer', '2201234', ['organization_id' => $this->digits->id, 'first_name' => 'MJ', 'last_name' => 'Labastida']);
    }

    // ---------- For Review ----------

    public function test_for_review_lists_one_card_per_ticket_and_a_form_per_document(): void
    {
        $single = $this->ticket(1, 'REF-1');
        $batch = $this->ticket(3, 'REF-2');

        $this->actingAs($this->adviser)->get("/signatory/review?ticket={$batch->id}")
            ->assertOk()
            ->assertViewHas('queue', fn ($queue) => $queue->count() === 2)
            ->assertViewHas('selected', fn ($item) => $item->ticket->is($batch) && $item->documents->count() === 3)
            ->assertSee('Step 1 of 2 · Adviser')
            ->assertSee('After you approve, the papers move to Step 2 · VPSD')
            ->assertSee("/signatory/tickets/{$batch->id}/approve-all")
            ->assertSee("/signatory/documents/{$batch->documents[2]->id}/action");

        // A Single ticket has no "Approve all" button
        $this->actingAs($this->adviser)->get("/signatory/review?ticket={$single->id}")
            ->assertDontSee('/approve-all');
    }

    public function test_for_review_tabs_split_single_and_batch(): void
    {
        $this->ticket(1, 'REF-1');
        $this->ticket(2, 'REF-2');

        $this->actingAs($this->adviser)->get('/signatory/review?type=Batch')
            ->assertViewHas('counts', ['All' => 2, 'Single' => 1, 'Batch' => 1])
            ->assertViewHas('queue', fn ($queue) => $queue->sole()->ticket->ref_no === 'REF-2');
    }

    // ---------- Approve all ----------

    public function test_approve_all_approves_only_the_papers_waiting_for_me(): void
    {
        $batch = $this->ticket(3, 'REF-2');
        [$first, $second, $third] = $batch->documents;
        $this->actingAs($this->adviser)->post("/signatory/documents/{$third->id}/action", ['action' => 'return', 'remarks' => 'Fix it']);

        $this->actingAs($this->adviser)->from('/signatory/review')
            ->post("/signatory/tickets/{$batch->id}/approve-all")
            ->assertRedirect('/signatory/review')
            ->assertSessionHas('status', fn ($msg) => str_contains($msg, 'Approved 2 documents') && str_contains($msg, 'Santos, Liza (VPSD)'));

        $this->assertSame([2, 2], [$first->fresh()->current_step, $second->fresh()->current_step]);
        $this->assertSame(Document::RETURNED, $third->fresh()->status);   // untouched
        // Each paper still gets its own line of history
        $this->assertSame(2, DocumentAction::where('action', DocumentAction::APPROVED)->count());
    }

    public function test_approve_all_does_nothing_for_a_signatory_out_of_turn(): void
    {
        $batch = $this->ticket(2, 'REF-2');

        $this->actingAs($this->vpsd)->post("/signatory/tickets/{$batch->id}/approve-all")->assertSessionHasErrors('action');

        $this->assertSame([1, 1], $batch->documents->map(fn ($d) => $d->fresh()->current_step)->all());
        $this->assertSame(0, DocumentAction::count());
    }

    // ---------- History ----------

    public function test_history_shows_only_my_actions_with_tabs_and_search(): void
    {
        $a = $this->ticket(1, 'REF-1')->documents->first();
        $b = $this->ticket(1, 'REF-2')->documents->first();
        $this->actingAs($this->adviser)->post("/signatory/documents/{$a->id}/action", ['action' => 'approve']);
        $this->actingAs($this->adviser)->post("/signatory/documents/{$b->id}/action", ['action' => 'return', 'remarks' => 'Missing receipts']);
        $this->actingAs($this->officer)->post("/officer/documents/{$b->id}/resubmit");   // the officer's action: not in my history
        $this->actingAs($this->vpsd)->post("/signatory/documents/{$a->id}/action", ['action' => 'approve']);   // someone else's

        $this->actingAs($this->adviser)->get('/signatory/history')
            ->assertOk()
            ->assertViewHas('counts', fn ($c) => $c->all() === ['All' => 2, 'Approved' => 1, 'Needs Revision' => 1, 'Rejected' => 0])
            ->assertSee('Missing receipts');

        $this->actingAs($this->adviser)->get('/signatory/history?tab=Needs+Revision')
            ->assertViewHas('actions', fn ($p) => $p->total() === 1 && $p->first()->remarks === 'Missing receipts');

        $code = $a->ticket->code;
        $this->actingAs($this->adviser)->get('/signatory/history?q='.$code)
            ->assertViewHas('actions', fn ($p) => $p->total() === 1 && $p->first()->document_id === $a->id);
        $this->actingAs($this->adviser)->get('/signatory/history?q=Labastida')
            ->assertViewHas('actions', fn ($p) => $p->total() === 2);
        $this->actingAs($this->adviser)->get('/signatory/history?q=nobody')
            ->assertSee('Nothing matches your search.');
    }

    public function test_history_shows_ten_per_page(): void
    {
        foreach (range(1, 12) as $i) {
            $doc = $this->ticket(1, "REF-{$i}")->documents->first();
            $this->actingAs($this->adviser)->post("/signatory/documents/{$doc->id}/action", ['action' => 'approve']);
        }

        $this->actingAs($this->adviser)->get('/signatory/history')
            ->assertViewHas('actions', fn ($p) => $p->count() === 10 && $p->total() === 12)
            ->assertSee('Showing 1–10 of 12');
        $this->actingAs($this->adviser)->get('/signatory/history?page=2')
            ->assertViewHas('actions', fn ($p) => $p->count() === 2);
    }

    // ---------- Profile ----------

    public function test_profile_shows_the_route_and_the_sidebar_shows_the_organization(): void
    {
        $this->actingAs($this->adviser)->get('/signatory/profile')
            ->assertOk()
            ->assertSee('Step 1 of 2')
            ->assertSee('DIGITS (Adviser)')
            ->assertSee('Adviser · DIGITS');
    }

    public function test_a_signatory_can_change_only_email_and_phone(): void
    {
        $this->actingAs($this->adviser)->put('/signatory/profile', [
            'email' => 'ana@lnu.edu.ph', 'phone' => '09181234567',
            'first_name' => 'Hacker', 'login_id' => 'EMP-999', 'position' => 'VPSD', 'role' => 'admin',
        ])->assertSessionHas('status', 'Profile saved.');

        $this->adviser->refresh();
        $this->assertSame(['ana@lnu.edu.ph', '09181234567'], [$this->adviser->email, $this->adviser->phone]);
        $this->assertSame(['Ana', 'EMP-1', 'Adviser', 'signatory'],
            [$this->adviser->first_name, $this->adviser->login_id, $this->adviser->position, $this->adviser->role]);

        $this->actingAs($this->adviser)->put('/signatory/profile', ['email' => 'ana@lnu.edu.ph', 'phone' => '12345'])
            ->assertSessionHasErrors('phone');
    }

    public function test_a_signatory_changes_their_password(): void
    {
        $this->actingAs($this->adviser)->put('/signatory/profile/password', [
            'current_password' => 'wrong', 'password' => 'NewPass#2026', 'password_confirmation' => 'NewPass#2026',
        ])->assertSessionHasErrorsIn('password', 'current_password');

        $this->actingAs($this->adviser)->put('/signatory/profile/password', [
            'current_password' => 'password', 'password' => 'NewPass#2026', 'password_confirmation' => 'NewPass#2026',
        ])->assertSessionHas('status', 'Password updated.');

        $this->assertTrue(Hash::check('NewPass#2026', $this->adviser->fresh()->password));
    }

    public function test_officers_cannot_open_signatory_pages(): void
    {
        foreach (['/signatory/review', '/signatory/history', '/signatory/profile'] as $page) {
            $this->actingAs($this->officer)->get($page)->assertForbidden();
        }
    }

    // ---------- Helpers ----------

    private function ticket(int $documents, string $refNo): Ticket
    {
        $ticket = new Ticket(['ref_no' => $refNo, 'title' => "Ticket {$refNo}"]);
        $ticket->code = Ticket::nextCode();
        $ticket->organization()->associate($this->digits);
        $ticket->officer()->associate($this->officer);
        $ticket->save();

        foreach (range(1, $documents) as $i) {
            $ticket->documents()->create(['type' => 'Resolution', 'title' => "Paper {$i} of {$refNo}"]);
        }

        return $ticket->load('documents');
    }

    private function makeUser(string $role, string $loginId, array $extra = []): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'verified', 'login_id' => $loginId] + $extra);
    }
}
