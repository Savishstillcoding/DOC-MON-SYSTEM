<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The officer's Dashboard and Signatories page show real data (AUD-038),
 * in Philippine time (AUD-051).
 */
class OfficerDashboardTest extends TestCase
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
        $this->adviser = $this->makeUser('signatory', 'EMP-1', ['first_name' => 'Ana', 'last_name' => 'Dela Cruz', 'availability' => 'Available']);
        $this->vpsd = $this->makeUser('signatory', 'EMP-2', ['first_name' => 'Liza', 'last_name' => 'Santos']);
        $this->digits->signatories()->attach($this->adviser, ['step' => 1, 'title' => 'Adviser']);
        $this->digits->signatories()->attach($this->vpsd, ['step' => 2, 'title' => 'VPSD']);
        $this->officer = $this->makeUser('officer', '2201234', [
            'organization_id' => $this->digits->id, 'position' => 'Secretary', 'first_name' => 'MJ', 'last_name' => 'Labastida',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_the_dashboard_shows_the_signatorys_real_availability(): void
    {
        // The live bug: the Adviser was Available, the dashboard still said "Meeting · 2 PM"
        $this->actingAs($this->officer)->get('/officer/dashboard')
            ->assertOk()
            ->assertDontSee('Meeting')
            ->assertDontSee('DM-00114');   // none of the old sample data

        $this->adviser->forceFill(['availability' => 'In Class', 'back_at' => '3:00 PM'])->save();

        $this->actingAs($this->officer)->get('/officer/dashboard')
            ->assertSee('In Class · 3:00 PM')
            ->assertSee('Step 1 · Adviser');
    }

    public function test_the_numbers_cards_and_activity_come_from_my_tickets(): void
    {
        $approved = $this->ticket('REF-1');
        $returned = $this->ticket('REF-2');
        $this->ticket('REF-3');
        $this->actingAs($this->adviser)->post("/signatory/documents/{$approved->documents[0]->id}/action", ['action' => 'approve']);
        $this->actingAs($this->vpsd)->post("/signatory/documents/{$approved->documents[0]->id}/action", ['action' => 'approve']);
        $this->actingAs($this->adviser)->post("/signatory/documents/{$returned->documents[0]->id}/action", ['action' => 'return', 'remarks' => 'Fix it']);

        // Another officer's ticket must not be counted
        $other = $this->makeUser('officer', '2209999', ['organization_id' => $this->digits->id]);
        $this->ticket('REF-9', $other);

        $this->actingAs($this->officer)->get('/officer/dashboard')
            ->assertViewHas('stats', ['total' => 3, 'pending' => 1, 'approved' => 1, 'revision' => 1, 'rejected' => 0])
            ->assertSee('1 ticket is waiting on signatories')
            ->assertSee('1 needs your revision')
            ->assertSee("{$approved->code} · Paper of REF-1 approved by VPSD")
            ->assertSee("{$returned->code} · Paper of REF-2 returned for revision by Adviser")
            ->assertDontSee('REF-9');

        // The tabs filter the cards
        $this->actingAs($this->officer)->get('/officer/dashboard?status=Needs+Revision')
            ->assertViewHas('cards', fn ($cards) => $cards->sole()['code'] === $returned->code);
    }

    public function test_the_greeting_and_date_use_philippine_time(): void
    {
        $this->assertSame('Asia/Manila', config('app.timezone'));

        // Sunday 5:30 PM in UTC is already Monday 1:30 AM in the Philippines:
        // with the old UTC setting this page said "SUNDAY, NOVEMBER 1" and "Good evening"
        Carbon::setTestNow(Carbon::parse('2026-11-01 17:30:00', 'UTC'));

        $this->actingAs($this->officer)->get('/officer/dashboard')
            ->assertSee('MONDAY, NOVEMBER 2, 2026')
            ->assertSee('Good morning, Secretary!')
            ->assertDontSee('FRIDAY, OCTOBER 16, 2026');
    }

    public function test_a_new_officer_sees_empty_states(): void
    {
        $this->actingAs($this->officer)->get('/officer/dashboard')
            ->assertSee('No tickets yet. Submit your first one')
            ->assertSee('Submissions, approvals and returns on your tickets will show here.');
    }

    public function test_the_signatories_page_shows_where_my_papers_are(): void
    {
        $a = $this->ticket('REF-1');
        $b = $this->ticket('REF-2');
        $this->actingAs($this->adviser)->post("/signatory/documents/{$a->documents[0]->id}/action", ['action' => 'approve']);
        $this->vpsd->forceFill(['availability' => 'Out of Office', 'back_at' => 'Tomorrow'])->save();

        $this->actingAs($this->officer)->get('/officer/signatories')
            ->assertOk()
            ->assertSee('The VPSD has the final say.')
            ->assertSee('Tomorrow')
            ->assertViewHas('signatories', function ($list) {
                [$adviser, $vpsd] = $list->all();

                return [$adviser->pending, $adviser->approved, $vpsd->pending, $vpsd->availability]
                    === [1, 1, 1, 'Out of Office'];
            })
            ->assertDontSee('Garcia, Ramon');   // old sample data
    }

    public function test_an_organization_without_a_route_gets_a_clear_message(): void
    {
        $lonely = $this->makeUser('officer', '2205555', [
            'organization_id' => Organization::create(['code' => 'NEW', 'name' => 'New Org'])->id,
        ]);

        $this->actingAs($lonely)->get('/officer/signatories')->assertSee('has no approval route yet');
        $this->actingAs($lonely)->get('/officer/dashboard')->assertSee('has no approval route yet');
    }

    // ---------- Helpers ----------

    private function ticket(string $refNo, ?User $officer = null): Ticket
    {
        $ticket = new Ticket(['ref_no' => $refNo, 'title' => "Ticket {$refNo}"]);
        $ticket->code = Ticket::nextCode();
        $ticket->organization()->associate($this->digits);
        $ticket->officer()->associate($officer ?? $this->officer);
        $ticket->save();
        $ticket->documents()->create(['type' => 'Resolution', 'title' => "Paper of {$refNo}"]);

        return $ticket->load('documents');
    }

    private function makeUser(string $role, string $loginId, array $extra = []): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'verified', 'login_id' => $loginId] + $extra);
    }
}
