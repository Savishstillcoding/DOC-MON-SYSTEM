<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * a fully rejected ticket frees its Ref No., so the officer can file the
 * corrected paper under the same number. Anything still open (pending, returned,
 * partly approved) keeps it.
 */
class RefNoReuseTest extends TestCase
{
    use RefreshDatabase;

    private Organization $digits;

    private User $adviser;

    private User $vpsd;

    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->digits = Organization::create(['code' => 'DIGITS', 'name' => 'DIGITS']);
        $this->adviser = $this->makeUser('signatory', 'EMP-1');
        $this->vpsd = $this->makeUser('signatory', 'EMP-2');
        $this->digits->signatories()->attach($this->adviser, ['step' => 1, 'title' => 'Adviser']);
        $this->digits->signatories()->attach($this->vpsd, ['step' => 2, 'title' => 'VPSD']);
        $this->officer = $this->makeUser('officer', '2201234', ['organization_id' => $this->digits->id]);
    }

    public function test_a_fully_rejected_ticket_frees_its_ref_no_for_a_new_ticket(): void
    {
        $old = $this->submit();
        $this->act($this->adviser, $old->documents[0], 'reject');

        $this->assertNull($old->fresh()->active_ref_no);
        $this->actingAs($this->officer)->get('/officer/tickets')
            ->assertSee('you can use Ref No. 2026-11-001 again');

        $this->actingAs($this->officer)->post('/officer/tickets', $this->form())->assertSessionHasNoErrors();

        $this->assertSame(2, Ticket::where('ref_no', '2026-11-001')->count());
        $this->assertSame('2026-11-001', $old->fresh()->ref_no);   // the old ticket keeps its number on record
        $this->assertSame('Rejected', $old->fresh()->status());
    }

    public function test_an_open_ticket_keeps_its_ref_no(): void
    {
        $ticket = $this->submit();
        $this->act($this->adviser, $ticket->documents[0], 'return');   // returned is still open

        $this->actingAs($this->officer)->post('/officer/tickets', $this->form())
            ->assertSessionHasErrors(['ref_no' => 'Your organization already has an open ticket with this Ref No.']);
        $this->assertSame('2026-11-001', $ticket->fresh()->active_ref_no);
    }

    public function test_a_batch_frees_its_ref_no_only_when_every_paper_is_rejected(): void
    {
        $ticket = $this->submit(2);
        [$one, $two] = $ticket->documents;

        $this->act($this->adviser, $one, 'reject');
        $this->assertSame('2026-11-001', $ticket->fresh()->active_ref_no, 'one paper is still pending');

        $this->act($this->adviser, $two, 'reject');
        $this->assertNull($ticket->fresh()->active_ref_no);
    }

    public function test_a_partly_approved_batch_keeps_its_ref_no(): void
    {
        $ticket = $this->submit(2);
        [$one, $two] = $ticket->documents;

        $this->act($this->adviser, $one, 'reject');
        $this->act($this->adviser, $two, 'approve');
        $this->act($this->vpsd, $two->fresh(), 'approve');   // final

        $this->assertSame('Partly Approved', $ticket->fresh()->status());
        $this->assertSame('2026-11-001', $ticket->fresh()->active_ref_no);
    }

    // ---------- helpers ----------

    private function submit(int $papers = 1): Ticket
    {
        $docs = [];
        for ($i = 0; $i < $papers; $i++) {
            $docs[] = ['type' => 'Activity Proposal', 'title' => 'Paper '.($i + 1)];
        }
        $this->actingAs($this->officer)->post('/officer/tickets', $this->form(['documents' => $docs]))->assertSessionHasNoErrors();

        return Ticket::with('documents')->latest('id')->first();
    }

    private function act(User $signatory, Document $document, string $action): void
    {
        $this->actingAs($signatory)->post("/signatory/documents/{$document->id}/action",
            ['action' => $action, 'remarks' => $action === 'approve' ? null : 'Wrong figures'])
            ->assertSessionHasNoErrors();
    }

    private function form(array $overrides = []): array
    {
        return array_merge([
            'ref_no' => '2026-11-001',
            'title' => 'DIGITS Week 2026',
            'documents' => [['type' => 'Activity Proposal', 'title' => 'Proposal']],
        ], $overrides);
    }

    private function makeUser(string $role, string $loginId, array $extra = []): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'verified', 'login_id' => $loginId] + $extra);
    }
}
