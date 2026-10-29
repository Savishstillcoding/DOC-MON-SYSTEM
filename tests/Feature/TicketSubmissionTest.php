<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Submitting a ticket (New Ticket form) and seeing it on My Tickets (AUD-018).
 */
class TicketSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private Organization $digits;

    private User $officer;

    private User $adviser;

    protected function setUp(): void
    {
        parent::setUp();

        // DIGITS with a 2-step route and one verified officer
        $this->digits = Organization::create(['code' => 'DIGITS', 'name' => 'DIGITS']);
        $this->adviser = $this->makeUser('signatory', 'EMP-1', ['first_name' => 'Ana', 'last_name' => 'Dela Cruz']);
        $this->digits->signatories()->attach($this->adviser, ['step' => 1, 'title' => 'Adviser']);
        $this->digits->signatories()->attach($this->makeUser('signatory', 'EMP-2'), ['step' => 2, 'title' => 'VPSD']);
        $this->officer = $this->makeUser('officer', '2201234', ['organization_id' => $this->digits->id]);
    }

    // ---------- Submitting ----------

    public function test_an_officer_submits_a_single_ticket(): void
    {
        $this->actingAs($this->officer)
            ->post('/officer/tickets', $this->form())
            ->assertRedirect('/officer/tickets')
            ->assertSessionHas('status', fn ($msg) => str_contains($msg, 'Dela Cruz, Ana (Adviser)'));

        $ticket = Ticket::with('documents')->sole();
        $this->assertSame('DM-'.now()->year.'-00001', $ticket->code);
        $this->assertSame($this->digits->id, $ticket->organization_id);
        $this->assertSame($this->officer->id, $ticket->officer_id);
        $this->assertSame('Single', $ticket->typeLabel());
        $this->assertSame('Pending', $ticket->status());

        $document = $ticket->documents->sole();
        $this->assertSame('pending', $document->fresh()->status);
        $this->assertSame(1, $document->fresh()->current_step);
    }

    public function test_a_batch_ticket_saves_every_document_and_codes_count_up(): void
    {
        $this->actingAs($this->officer)->post('/officer/tickets', $this->form());
        $this->actingAs($this->officer)->post('/officer/tickets', $this->form([
            'ref_no' => '2026-09-009',
            'documents' => [
                ['type' => 'Resolution', 'title' => 'Resolution', 'ref_no' => ''],
                ['type' => 'Activity Proposal', 'title' => 'Proposal', 'ref_no' => 'AP-01'],
                ['type' => 'Budgetary', 'title' => 'Budget', 'ref_no' => ''],
            ],
        ]));

        $batch = Ticket::with('documents')->where('ref_no', '2026-09-009')->sole();
        $this->assertSame('DM-'.now()->year.'-00002', $batch->code);
        $this->assertSame('Batch (3)', $batch->typeLabel());
        $this->assertSame(['Resolution', 'Activity Proposal', 'Budgetary'], $batch->documents->pluck('type')->all());
    }

    public function test_the_organization_comes_from_the_officer_not_the_form(): void
    {
        $other = Organization::create(['code' => 'OTHER', 'name' => 'Other Org']);

        $this->actingAs($this->officer)->post('/officer/tickets', $this->form([
            'organization' => 'OTHER',
            'organization_id' => $other->id,
        ]));

        $this->assertSame($this->digits->id, Ticket::sole()->organization_id);
    }

    // ---------- Rules ----------

    public function test_a_ref_no_cannot_be_reused_in_the_same_organization(): void
    {
        $this->actingAs($this->officer)->post('/officer/tickets', $this->form());

        $this->actingAs($this->officer)
            ->post('/officer/tickets', $this->form())
            ->assertSessionHasErrors(['ref_no' => 'Your organization already used this Ref No.']);

        $this->assertSame(1, Ticket::count());
    }

    public function test_required_fields_and_valid_values_are_checked(): void
    {
        $this->actingAs($this->officer)
            ->post('/officer/tickets', [
                'ref_no' => '',
                'title' => '',
                'date_needed' => now()->subDay()->toDateString(),
                'documents' => [['type' => 'Excuse Letter', 'title' => '']],
            ])
            ->assertSessionHasErrors([
                'ref_no', 'title',
                'date_needed' => 'The date needed can\'t be in the past.',
                'documents.0.type' => 'Document 1: choose a type from the list.',
                'documents.0.title' => 'Document 1 needs a title.',
            ]);

        $this->assertSame(0, Ticket::count());
    }

    public function test_a_ticket_needs_at_least_one_document(): void
    {
        $this->actingAs($this->officer)
            ->post('/officer/tickets', $this->form(['documents' => []]))
            ->assertSessionHasErrors(['documents' => 'Add at least one document.']);
    }

    public function test_a_document_ref_no_cannot_repeat_within_the_organization(): void
    {
        $this->actingAs($this->officer)->post('/officer/tickets', $this->form([
            'documents' => [['type' => 'Activity Proposal', 'title' => 'Proposal', 'ref_no' => 'AP-01']],
        ]));

        $this->actingAs($this->officer)
            ->post('/officer/tickets', $this->form([
                'ref_no' => '2026-09-009',
                'documents' => [
                    ['type' => 'Resolution', 'title' => 'Resolution', 'ref_no' => 'AP-01'],
                    ['type' => 'Budgetary', 'title' => 'Budget', 'ref_no' => 'B-1'],
                    ['type' => 'Budgetary', 'title' => 'Budget copy', 'ref_no' => 'B-1'],
                ],
            ]))
            ->assertSessionHasErrors([
                'documents.0.ref_no' => 'Document 1: your organization already used this Ref No.',
                'documents.2.ref_no' => 'Document 3: the same Ref No. is used twice in this ticket.',
            ]);
    }

    public function test_an_organization_without_signatories_cannot_receive_tickets(): void
    {
        $empty = Organization::create(['code' => 'NEW', 'name' => 'New Org']);
        $officer = $this->makeUser('officer', '2209999', ['organization_id' => $empty->id]);

        $this->actingAs($officer)
            ->post('/officer/tickets', $this->form())
            ->assertSessionHasErrors('ticket');

        $this->assertSame(0, Ticket::count());
    }

    public function test_only_officers_can_submit_tickets(): void
    {
        $this->actingAs($this->adviser)->post('/officer/tickets', $this->form())->assertForbidden();
        $this->assertSame(0, Ticket::count());
    }

    // ---------- My Tickets ----------

    public function test_my_tickets_lists_only_the_officers_own_tickets(): void
    {
        $this->actingAs($this->officer)->post('/officer/tickets', $this->form(['title' => 'Mine']));

        $classmate = $this->makeUser('officer', '2205555', ['organization_id' => $this->digits->id]);
        $this->actingAs($classmate)->post('/officer/tickets', $this->form(['ref_no' => '2026-09-099', 'title' => 'Not mine']));

        $this->actingAs($this->officer)
            ->get('/officer/tickets')
            ->assertOk()
            ->assertSee('Mine')
            ->assertSee('Dela Cruz, Ana')   // current signatory: step 1
            ->assertDontSee('Not mine');
    }

    public function test_the_new_ticket_page_shows_the_real_approval_route(): void
    {
        $this->actingAs($this->officer)
            ->get('/officer/tickets/new')
            ->assertOk()
            ->assertSeeInOrder(['DIGITS signatories, in order', 'Dela Cruz, Ana', 'Adviser', 'VPSD']);
    }

    // ---------- Helpers ----------

    /** A valid New Ticket form, with any fields replaced. */
    private function form(array $overrides = []): array
    {
        return array_merge([
            'ref_no' => '2026-09-008',
            'title' => 'DIGITS Week 2026',
            'date_needed' => now()->addWeek()->toDateString(),
            'purpose' => 'For approval',
            'documents' => [['type' => 'Activity Proposal', 'title' => 'DIGITS Week Proposal', 'ref_no' => '']],
        ], $overrides);
    }

    private function makeUser(string $role, string $loginId, array $extra = []): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'verified', 'login_id' => $loginId] + $extra);
    }
}
