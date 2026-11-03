<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The search boxes (AUD-021): the Dashboards' boxes did nothing; now the officer's
 * searches My Tickets and the signatory's searches For Review, and both of those
 * pages have their own box.
 */
class SearchTest extends TestCase
{
    use RefreshDatabase;

    private Organization $digits;

    private User $officer;

    private User $other;     // another officer of the same organization

    private User $adviser;   // step 1

    private User $vpsd;      // step 2

    private Ticket $week;

    private Ticket $seminar;

    private Ticket $othersWeek;

    protected function setUp(): void
    {
        parent::setUp();

        $this->digits = Organization::create(['code' => 'DIGITS', 'name' => 'DIGITS']);
        $this->adviser = $this->makeUser('signatory', 'EMP-1');
        $this->vpsd = $this->makeUser('signatory', 'EMP-2');
        $this->digits->signatories()->attach($this->adviser, ['step' => 1, 'title' => 'Adviser']);
        $this->digits->signatories()->attach($this->vpsd, ['step' => 2, 'title' => 'VPSD']);
        $this->officer = $this->makeUser('officer', '2201234', ['first_name' => 'MJ', 'last_name' => 'Labastida']);
        $this->other = $this->makeUser('officer', '2209999', ['first_name' => 'Jessa', 'last_name' => 'Villanueva']);

        $this->week = $this->ticket($this->officer, '2026-11-001', 'DIGITS Week', [['Budget Plan', null], ['Program Flow', null]]);
        $this->seminar = $this->ticket($this->officer, '2026-11-002', 'Seminar', [['Letter', 'RES-77']]);
        $this->othersWeek = $this->ticket($this->other, '2026-11-003', 'DIGITS Week Too', [['Budget Plan', null]]);
    }

    // ---------- Officer: My Tickets ----------

    public function test_my_tickets_finds_by_ticket_ref_paper_and_code(): void
    {
        $found = fn (string $q) => $this->actingAs($this->officer)->get('/officer/tickets?q='.urlencode($q))
            ->assertOk()->viewData('tickets')->pluck('code')->values()->all();

        $this->assertSame([$this->week->code], $found('digits week'));      // title, any case
        $this->assertSame([$this->week->code], $found('budget'));           // a paper's title
        $this->assertSame([$this->seminar->code], $found('RES-77'));        // a paper's Ref No.
        $this->assertSame([$this->seminar->code], $found('11-002'));        // the ticket's Ref No.
        $this->assertSame([$this->seminar->code], $found($this->seminar->code));
        $this->assertSame([], $found('nothing like this'));
    }

    public function test_a_search_never_shows_another_officers_tickets(): void
    {
        // Their ticket matches too ("DIGITS Week Too", "Budget Plan", officer name),
        // but the ORs in the search must stay inside "my tickets"
        foreach (['DIGITS', 'Budget', 'Villanueva', $this->othersWeek->code] as $q) {
            $codes = $this->actingAs($this->officer)->get('/officer/tickets?q='.urlencode($q))->viewData('tickets')->pluck('code');
            $this->assertNotContains($this->othersWeek->code, $codes, "search: {$q}");
        }
    }

    public function test_the_tabs_count_and_keep_the_search(): void
    {
        $this->actingAs($this->officer)->get('/officer/tickets?q=budget')
            ->assertViewHas('counts', fn ($c) => $c['All'] === 1 && $c['Pending'] === 1)
            ->assertSee('value="budget"', false)
            ->assertSee('href="'.url('/officer/tickets?status=Pending&amp;q=budget').'"', false)
            ->assertSee('matching “budget”');

        // A tab and a search together
        $this->actingAs($this->officer)->get('/officer/tickets?status=Approved&q=budget')
            ->assertViewHas('tickets', fn ($t) => $t->isEmpty())
            ->assertSee('<input type="hidden" name="status" value="Approved">', false)
            ->assertSee('No tickets match “budget”.');
    }

    // ---------- Signatory: For Review ----------

    public function test_for_review_finds_by_officer_and_keeps_a_batch_whole(): void
    {
        $queue = fn (string $q) => $this->actingAs($this->adviser)->get('/signatory/review?q='.urlencode($q))
            ->assertOk()->viewData('queue');

        $this->assertSame([$this->othersWeek->code], $queue('villanueva')->pluck('ticket.code')->all());

        // "Program Flow" is one paper of a 2-paper Batch: the ticket shows with both papers
        $item = $queue('program flow')->sole();
        $this->assertSame($this->week->code, $item->ticket->code);
        $this->assertSame(['Budget Plan', 'Program Flow'], $item->documents->pluck('title')->sort()->values()->all());

        $this->assertCount(3, $queue(''));
        $this->actingAs($this->adviser)->get('/signatory/review?q=zzz')->assertSee('Nothing waiting matches “zzz”.');
    }

    public function test_for_review_search_only_looks_at_papers_waiting_for_me(): void
    {
        // Everything is at step 1 (the Adviser): the VPSD finds nothing, whatever the words
        $this->actingAs($this->vpsd)->get('/signatory/review?q=DIGITS')->assertViewHas('queue', fn ($q) => $q->isEmpty());
    }

    // ---------- The Dashboards' boxes ----------

    public function test_the_dashboard_search_boxes_go_to_the_full_lists(): void
    {
        // Before: no form, no name, no label; typing did nothing
        $this->actingAs($this->officer)->get('/officer/dashboard')
            ->assertSee('<form method="GET" action="'.url('/officer/tickets').'" class="dm-header-actions">', false)
            ->assertSee('name="q" placeholder="Search ticket, Ref No. or paper" aria-label="Search my tickets"', false);

        $this->actingAs($this->adviser)->get('/signatory/dashboard')
            ->assertSee('<form method="GET" action="'.url('/signatory/review').'" class="dm-header-actions">', false)
            ->assertSee('aria-label="Search the queue"', false);
    }

    // ---------- Helpers ----------

    /** @param  array<int, array{0: string, 1: ?string}>  $papers  [title, Ref No.] */
    private function ticket(User $officer, string $refNo, string $title, array $papers): Ticket
    {
        $ticket = new Ticket(['ref_no' => $refNo, 'title' => $title]);
        $ticket->code = Ticket::nextCode();
        $ticket->organization()->associate($this->digits);
        $ticket->officer()->associate($officer);
        $ticket->save();

        foreach ($papers as [$paper, $ref]) {
            $ticket->documents()->create(['type' => 'Resolution', 'title' => $paper, 'ref_no' => $ref]);
        }

        return $ticket;
    }

    private function makeUser(string $role, string $loginId, array $extra = []): User
    {
        return User::factory()->create([
            'role' => $role, 'status' => 'verified', 'login_id' => $loginId,
            'organization_id' => $role === 'officer' ? $this->digits->id : null,
        ] + $extra);
    }
}
