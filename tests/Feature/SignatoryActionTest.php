<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentAction;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Signatories approving, returning and rejecting documents, officers
 * resubmitting returned ones, and the ticket status that follows (AUD-046).
 */
class SignatoryActionTest extends TestCase
{
    use RefreshDatabase;

    private Organization $digits;

    private User $officer;

    private User $adviser;   // step 1

    private User $ssc;       // step 2

    private User $vpsd;      // step 3, final say

    protected function setUp(): void
    {
        parent::setUp();

        $this->digits = Organization::create(['code' => 'DIGITS', 'name' => 'DIGITS']);
        $this->adviser = $this->makeUser('signatory', 'EMP-1', ['first_name' => 'Ana', 'last_name' => 'Dela Cruz']);
        $this->ssc = $this->makeUser('signatory', 'EMP-2', ['first_name' => 'Paolo', 'last_name' => 'Reyes']);
        $this->vpsd = $this->makeUser('signatory', 'EMP-3');
        $this->digits->signatories()->attach($this->adviser, ['step' => 1, 'title' => 'Adviser']);
        $this->digits->signatories()->attach($this->ssc, ['step' => 2, 'title' => 'SSC President']);
        $this->digits->signatories()->attach($this->vpsd, ['step' => 3, 'title' => 'VPSD']);
        $this->officer = $this->makeUser('officer', '2201234', ['organization_id' => $this->digits->id]);
    }

    // ---------- Approve ----------

    public function test_approving_moves_the_document_to_the_next_signatory(): void
    {
        $doc = $this->ticket()->documents->first();

        $this->act($this->adviser, $doc, 'approve')
            ->assertRedirect('/signatory/dashboard')
            ->assertSessionHas('status', fn ($msg) => str_contains($msg, 'Reyes, Paolo (SSC President)'));

        $doc->refresh();
        $this->assertSame(Document::PENDING, $doc->status);
        $this->assertSame(2, $doc->current_step);

        $action = DocumentAction::sole();
        $this->assertSame([1, 'approved', $this->adviser->id], [$action->step, $action->action, $action->user_id]);
    }

    public function test_the_last_signatory_approval_is_final(): void
    {
        $doc = $this->ticket()->documents->first();

        $this->act($this->adviser, $doc, 'approve');
        $this->act($this->ssc, $doc, 'approve');
        $this->act($this->vpsd, $doc, 'approve')
            ->assertSessionHas('status', fn ($msg) => str_contains($msg, 'final signature'));

        $this->assertSame(Document::APPROVED, $doc->fresh()->status);
        $this->assertSame('Approved', $this->freshTicket($doc)->status());
        $this->assertSame([1, 2, 3], DocumentAction::orderBy('id')->pluck('step')->all());
    }

    // ---------- Return and reject ----------

    public function test_returning_needs_remarks_and_keeps_the_step(): void
    {
        $doc = $this->ticket()->documents->first();
        $this->act($this->adviser, $doc, 'approve');

        $this->act($this->ssc, $doc, 'return')->assertSessionHasErrors('remarks');
        $this->assertSame(Document::PENDING, $doc->fresh()->status);

        $this->act($this->ssc, $doc, 'return', 'Wrong venue in item 3')->assertSessionHasNoErrors();

        $doc->refresh();
        $this->assertSame(Document::RETURNED, $doc->status);
        $this->assertSame(2, $doc->current_step);
        $this->assertSame('Needs Revision', $this->freshTicket($doc)->status());
        $this->assertSame('Wrong venue in item 3', $doc->latestAction->remarks);
    }

    public function test_rejecting_needs_remarks_and_is_final(): void
    {
        $doc = $this->ticket()->documents->first();

        $this->act($this->adviser, $doc, 'reject')->assertSessionHasErrors('remarks');
        $this->act($this->adviser, $doc, 'reject', 'Activity not allowed this semester');

        $this->assertSame(Document::REJECTED, $doc->fresh()->status);
        $this->assertSame('Rejected', $this->freshTicket($doc)->status());

        // Nobody can act on it anymore, not even the same signatory
        $this->act($this->adviser, $doc, 'approve')->assertSessionHasErrors('action');
        $this->assertSame(Document::REJECTED, $doc->fresh()->status);
    }

    public function test_an_unknown_action_is_refused(): void
    {
        $doc = $this->ticket()->documents->first();

        $this->act($this->adviser, $doc, 'skip_to_vpsd')->assertSessionHasErrors('action');
        $this->assertSame(1, $doc->fresh()->current_step);
    }

    // ---------- Who may act ----------

    public function test_a_signatory_cannot_act_out_of_turn(): void
    {
        $doc = $this->ticket()->documents->first();

        // The VPSD tries to sign before the Adviser
        $this->act($this->vpsd, $doc, 'approve')->assertSessionHasErrors('action');

        $this->assertSame(1, $doc->fresh()->current_step);
        $this->assertSame(0, DocumentAction::count());
    }

    public function test_a_signatory_cannot_act_on_another_organizations_document(): void
    {
        $other = Organization::create(['code' => 'OTHER', 'name' => 'Other Org']);
        $outsider = $this->makeUser('signatory', 'EMP-9');
        $other->signatories()->attach($outsider, ['step' => 1, 'title' => 'Adviser']);

        // Step 1 in their own organization, but this is a DIGITS document
        $doc = $this->ticket()->documents->first();
        $this->act($outsider, $doc, 'approve')->assertSessionHasErrors('action');

        $this->assertSame(1, $doc->fresh()->current_step);
        $this->assertSame(0, DocumentAction::count());
    }

    public function test_a_second_click_on_the_same_document_does_nothing(): void
    {
        $doc = $this->ticket()->documents->first();

        $this->act($this->adviser, $doc, 'approve');
        $this->act($this->adviser, $doc, 'approve')->assertSessionHasErrors('action');

        $this->assertSame(2, $doc->fresh()->current_step);
        $this->assertSame(1, DocumentAction::count());
    }

    public function test_officers_cannot_use_the_signatory_action(): void
    {
        $doc = $this->ticket()->documents->first();

        $this->actingAs($this->officer)
            ->post("/signatory/documents/{$doc->id}/action", ['action' => 'approve'])
            ->assertForbidden();
    }

    // ---------- Resubmit ----------

    public function test_a_resubmitted_document_goes_back_to_the_same_signatory(): void
    {
        $doc = $this->ticket()->documents->first();
        $this->act($this->adviser, $doc, 'approve');
        $this->act($this->ssc, $doc, 'return', 'Add the budget breakdown');

        $this->actingAs($this->officer)
            ->post("/officer/documents/{$doc->id}/resubmit")
            ->assertSessionHas('status', fn ($msg) => str_contains($msg, 'Reyes, Paolo (SSC President)'));

        $doc->refresh();
        $this->assertSame(Document::PENDING, $doc->status);
        $this->assertSame(2, $doc->current_step);   // the Adviser's approval still counts
        $this->assertSame(DocumentAction::RESUBMITTED, $doc->latestAction->action);
        $this->assertSame($this->officer->id, $doc->latestAction->user_id);

        // It is in the SSC President's queue again, and the SSC President can act
        $this->actingAs($this->ssc)->get('/signatory/dashboard')->assertSee('DIGITS Week Proposal');
        $this->act($this->ssc, $doc, 'approve')->assertSessionHasNoErrors();
        $this->assertSame(3, $doc->fresh()->current_step);
    }

    public function test_only_the_ticket_owner_can_resubmit_and_only_returned_documents(): void
    {
        $doc = $this->ticket()->documents->first();
        $otherOfficer = $this->makeUser('officer', '2209999', ['organization_id' => $this->digits->id]);

        // Still pending: nothing to resubmit
        $this->actingAs($this->officer)->post("/officer/documents/{$doc->id}/resubmit")->assertSessionHasErrors('resubmit');

        $this->act($this->adviser, $doc, 'return', 'Fix the date');

        // Returned, but this officer didn't file the ticket
        $this->actingAs($otherOfficer)->post("/officer/documents/{$doc->id}/resubmit")->assertSessionHasErrors('resubmit');
        $this->assertSame(Document::RETURNED, $doc->fresh()->status);
    }

    // ---------- Batch and ticket status ----------

    public function test_a_batch_with_approved_and_rejected_papers_is_partly_approved(): void
    {
        [$proposal, $budget] = $this->ticket(2)->documents;

        $this->act($this->adviser, $budget, 'reject', 'Over the allowed amount');
        $this->assertSame('Pending', $this->freshTicket($proposal)->status());   // the proposal is still moving

        foreach ([$this->adviser, $this->ssc, $this->vpsd] as $signatory) {
            $this->act($signatory, $proposal, 'approve');
        }

        $this->assertSame('Partly Approved', $this->freshTicket($proposal)->status());
        $this->assertNull($this->freshTicket($proposal)->currentSignatory());
    }

    // ---------- What each side sees ----------

    public function test_the_dashboard_lists_only_documents_at_my_step(): void
    {
        $this->ticket();
        $moved = $this->ticket(1, '2026-09-020')->documents->first();
        $this->act($this->adviser, $moved, 'approve');   // now at the SSC President

        $this->actingAs($this->adviser)->get('/signatory/dashboard')
            ->assertOk()
            ->assertViewHas('documents', fn ($docs) => $docs->count() === 1)
            ->assertViewHas('stats', fn ($s) => $s['waiting'] === 1 && $s['signedThisWeek'] === 1)
            ->assertSee('You have 1 document waiting for your signature.');

        $this->actingAs($this->ssc)->get('/signatory/dashboard')
            ->assertViewHas('documents', fn ($docs) => $docs->sole()->is($moved));

        $this->actingAs($this->vpsd)->get('/signatory/dashboard')
            ->assertSee('No documents are waiting for your signature.');
    }

    public function test_my_tickets_shows_the_remarks_and_a_resubmit_button(): void
    {
        $doc = $this->ticket()->documents->first();
        $this->act($this->adviser, $doc, 'return', 'Please attach the program flow');

        $this->actingAs($this->officer)->get('/officer/tickets')
            ->assertOk()
            ->assertSee('Returned by Dela Cruz, Ana (Adviser)')
            ->assertSee('Please attach the program flow')
            ->assertSee("/officer/documents/{$doc->id}/resubmit");
    }

    // ---------- Helpers ----------

    /** A DIGITS ticket filed by the officer, with 1 (Single) or more (Batch) documents. */
    private function ticket(int $documents = 1, string $refNo = '2026-09-008'): Ticket
    {
        $ticket = new Ticket(['ref_no' => $refNo, 'title' => 'DIGITS Week 2026']);
        $ticket->code = Ticket::nextCode();
        $ticket->organization()->associate($this->digits);
        $ticket->officer()->associate($this->officer);
        $ticket->save();

        $titles = ['DIGITS Week Proposal', 'DIGITS Week Budget', 'DIGITS Week Resolution'];
        foreach (array_slice($titles, 0, $documents) as $title) {
            $ticket->documents()->create(['type' => 'Activity Proposal', 'title' => $title]);
        }

        return $ticket->load('documents');
    }

    private function act(User $signatory, Document $document, string $action, ?string $remarks = null)
    {
        return $this->actingAs($signatory)->from('/signatory/dashboard')->post("/signatory/documents/{$document->id}/action", [
            'action' => $action, 'remarks' => $remarks, 'document_id' => $document->id,
        ]);
    }

    private function freshTicket(Document $document): Ticket
    {
        return Ticket::with('documents', 'organization.signatories')->find($document->ticket_id);
    }

    private function makeUser(string $role, string $loginId, array $extra = []): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'verified', 'login_id' => $loginId] + $extra);
    }
}
