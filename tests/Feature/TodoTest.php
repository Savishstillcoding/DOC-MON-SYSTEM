<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AUD-097: the To Do box at the top of the officer Dashboard lists what the officer has to
 * act on (returned papers with the signatory's remark and a Resubmit button, then overdue
 * tickets and who they're waiting for), and the ticket cards come most urgent first.
 */
class TodoTest extends TestCase
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
        $this->adviser = $this->makeUser('signatory', 'EMP-1', ['first_name' => 'Ana', 'last_name' => 'Dela Cruz']);
        $this->vpsd = $this->makeUser('signatory', 'EMP-2', ['first_name' => 'Ramon', 'last_name' => 'Cruz',
            'availability' => 'Out of Office', 'back_at' => 'Tomorrow']);
        $this->digits->signatories()->attach($this->adviser, ['step' => 1, 'title' => 'Adviser']);
        $this->digits->signatories()->attach($this->vpsd, ['step' => 2, 'title' => 'VPSD']);
        $this->officer = $this->makeUser('officer', '2201234', ['organization_id' => $this->digits->id, 'position' => 'Secretary']);
    }

    public function test_a_returned_paper_shows_the_remark_and_can_be_resubmitted_from_the_dashboard(): void
    {
        $paper = $this->ticket('REF-1', 'Program Flow')->documents[0];
        $this->act($this->adviser, $paper, 'return', 'Add the venue.');

        $this->actingAs($this->officer)->get('/officer/dashboard')
            ->assertSeeInOrder(['To Do', '· 1', 'Needs Revision', 'Program Flow', 'Adviser: <q>Add the venue.</q>',
                'action="'.url("/officer/documents/{$paper->id}/resubmit").'"', 'Resubmit'], false);

        $this->actingAs($this->officer)->from('/officer/dashboard')->post("/officer/documents/{$paper->id}/resubmit")
            ->assertRedirect('/officer/dashboard')
            ->assertSessionHas('status');

        $this->assertSame(Document::PENDING, $paper->fresh()->status);
        $this->actingAs($this->officer)->get('/officer/dashboard')->assertSee('All clear: nothing to revise and nothing overdue.');
    }

    public function test_an_overdue_ticket_says_who_it_is_waiting_for(): void
    {
        $ticket = $this->ticket('REF-1', 'GA Resolution', now()->subDay());
        $this->act($this->adviser, $ticket->documents[0], 'approve');   // now with the VPSD

        $this->actingAs($this->officer)->get('/officer/dashboard')
            ->assertSeeInOrder(['To Do', 'Overdue', 'DIGITS Week', "{$ticket->code} · needed", 'Waiting for Cruz, Ramon (VPSD)',
                'Out of Office', 'Back at Tomorrow'], false);
    }

    public function test_due_soon_and_on_time_tickets_stay_out_of_to_do(): void
    {
        $this->ticket('REF-1', 'Soon', now()->addDays(2));
        $this->ticket('REF-2', 'Later', now()->addWeeks(2));
        $this->ticket('REF-3', 'No date');

        $this->actingAs($this->officer)->get('/officer/dashboard')
            ->assertViewHas('todo', fn ($todo) => $todo->isEmpty())
            ->assertSee('All clear: nothing to revise and nothing overdue.');
    }

    public function test_another_organizations_papers_never_appear(): void
    {
        $other = Organization::create(['code' => 'OTHER', 'name' => 'Other Org']);
        $other->signatories()->attach($this->adviser, ['step' => 1, 'title' => 'Adviser']);
        $outsider = $this->makeUser('officer', '2209999', ['organization_id' => $other->id]);
        $paper = $this->ticket('REF-9', 'Secret Paper', null, $outsider, $other)->documents[0];
        $this->act($this->adviser, $paper, 'return', 'Not ours');
        $this->flushSession();   // drop the adviser's own "Returned Secret Paper…" message from this test's shared session

        $this->actingAs($this->officer)->get('/officer/dashboard')
            ->assertDontSee('Secret Paper')
            ->assertViewHas('todo', fn ($todo) => $todo->isEmpty());
    }

    public function test_a_new_officer_is_told_to_submit_a_first_ticket(): void
    {
        $this->actingAs($this->officer)->get('/officer/dashboard')
            ->assertSee('No tickets yet. Submit your first one when your papers are ready.')
            ->assertDontSee('All clear');
    }

    public function test_the_cards_come_most_urgent_first(): void
    {
        $returned = $this->ticket('REF-1', 'Returned');                      // oldest
        $overdue = $this->ticket('REF-2', 'Overdue', now()->subDay());
        $soon = $this->ticket('REF-3', 'Soon', now()->addDays(2));
        $plain = $this->ticket('REF-4', 'Plain');                            // newest
        $this->act($this->adviser, $returned->documents[0], 'return', 'Fix it');

        $this->actingAs($this->officer)->get('/officer/dashboard')
            ->assertViewHas('cards', fn ($cards) => $cards->pluck('code')->all()
                === [$returned->code, $overdue->code, $soon->code, $plain->code]);
    }

    public function test_the_list_stops_at_five_and_links_to_the_rest(): void
    {
        foreach (range(1, 7) as $i) {
            $this->act($this->adviser, $this->ticket("REF-{$i}", "Paper {$i}")->documents[0], 'return', 'Fix it');
        }

        $this->actingAs($this->officer)->get('/officer/dashboard')
            ->assertViewHas('todo', fn ($todo) => $todo->count() === 5)
            ->assertSee('· 7')
            ->assertSee('2 more on My Tickets');
    }

    // ---------- helpers ----------

    private function ticket(string $refNo, string $paper, $needed = null, ?User $officer = null, ?Organization $org = null): Ticket
    {
        static $n = 0;
        $ticket = new Ticket(['ref_no' => $refNo, 'title' => 'DIGITS Week', 'date_needed' => $needed]);
        $ticket->code = Ticket::nextCode();
        $ticket->organization()->associate($org ?? $this->digits);
        $ticket->officer()->associate($officer ?? $this->officer);
        $ticket->created_at = now()->addMinutes(++$n);   // later tickets are newer
        $ticket->save();
        $ticket->documents()->create(['type' => 'Resolution', 'title' => $paper]);

        return $ticket->load('documents');
    }

    private function act(User $signatory, Document $document, string $action, ?string $remarks = null): void
    {
        $this->actingAs($signatory)->post("/signatory/documents/{$document->id}/action", ['action' => $action, 'remarks' => $remarks])
            ->assertSessionHasNoErrors();
    }

    private function makeUser(string $role, string $loginId, array $extra = []): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'verified', 'login_id' => $loginId] + $extra);
    }
}
