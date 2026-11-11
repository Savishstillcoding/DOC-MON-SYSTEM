<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AUD-077: the Date Needed is shown to officers, tickets get Overdue / Due Soon badges
 * while papers are still moving, and the signatory's queue puts the most urgent first.
 */
class DeadlineTest extends TestCase
{
    use RefreshDatabase;

    private Organization $digits;

    private User $officer;

    private User $adviser;

    private int $refs = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // A fixed "today", so the tests don't depend on the day they run
        $this->travelTo(now()->setDate(2026, 11, 10)->setTime(9, 0));

        $this->digits = Organization::create(['code' => 'DIGITS', 'name' => 'DIGITS']);
        $this->adviser = User::factory()->create(['role' => 'signatory', 'status' => 'verified', 'login_id' => 'EMP-1']);
        $this->digits->signatories()->attach($this->adviser, ['step' => 1, 'title' => 'Adviser']);
        $this->officer = User::factory()->create([
            'role' => 'officer', 'status' => 'verified', 'login_id' => '2201234',
            'organization_id' => $this->digits->id, 'position' => 'Secretary',
        ]);
    }

    public function test_the_badge_follows_the_date_needed(): void
    {
        $this->assertNull($this->ticket(null)->deadlineStatus());                 // no date
        $this->assertNull($this->ticket('2026-11-14')->deadlineStatus());         // 4 days left
        $this->assertSame('Due Soon', $this->ticket('2026-11-13')->deadlineStatus());   // 3 days left
        $this->assertSame('Due Soon', $this->ticket('2026-11-10')->deadlineStatus());   // today
        $this->assertSame('Overdue', $this->ticket('2026-11-09')->deadlineStatus());    // yesterday
    }

    public function test_a_returned_paper_can_be_overdue_but_a_finished_ticket_never_is(): void
    {
        $this->assertSame('Overdue', $this->ticket('2026-11-01', Document::RETURNED)->deadlineStatus());
        $this->assertNull($this->ticket('2026-11-01', Document::APPROVED)->deadlineStatus());
        $this->assertNull($this->ticket('2026-11-01', Document::REJECTED)->deadlineStatus());
    }

    public function test_the_badge_changes_by_itself_when_the_days_pass(): void
    {
        $ticket = $this->ticket('2026-11-20');
        $this->assertNull($ticket->deadlineStatus());

        $this->travelTo(now()->setDate(2026, 11, 18));
        $this->assertSame('Due Soon', $ticket->fresh()->deadlineStatus());

        $this->travelTo(now()->setDate(2026, 11, 21));
        $this->assertSame('Overdue', $ticket->fresh()->deadlineStatus());
    }

    public function test_the_signatory_sees_the_most_urgent_papers_first(): void
    {
        // Filed one after another, so without the new order the queue would be A, B, C, D, E
        $a = $this->ticket(null, title: 'No date, filed first');
        $b = $this->ticket('2026-11-25', title: 'Needed in 15 days');
        $c = $this->ticket('2026-11-05', title: 'Overdue');
        $d = $this->ticket('2026-11-12', title: 'Needed in 2 days');
        $e = $this->ticket('2026-11-12', title: 'Same date, filed later');
        $expected = [$c->id, $d->id, $e->id, $b->id, $a->id];

        $this->actingAs($this->adviser)->get('/signatory/review')
            ->assertOk()
            ->assertViewHas('queue', fn ($queue) => $queue->map(fn ($item) => $item->ticket->id)->all() === $expected)
            ->assertSee('most urgent first');

        $this->actingAs($this->adviser)->get('/signatory/dashboard')
            ->assertOk()
            ->assertViewHas('documents', fn ($documents) => $documents->map(fn ($doc) => $doc->ticket_id)->all() === $expected);
    }

    public function test_the_signatory_sees_the_badges_and_the_date(): void
    {
        $overdue = $this->ticket('2026-11-05');
        $this->ticket('2026-11-12');

        $this->actingAs($this->adviser)->get("/signatory/review?ticket={$overdue->id}")
            ->assertSee('Overdue')
            ->assertSee('Due Soon')
            ->assertSee('Needed Nov 5')
            ->assertSee('Nov 5, 2026');   // the selected ticket's "Date needed"

        $this->actingAs($this->adviser)->get('/signatory/dashboard')
            ->assertSee('Overdue')
            ->assertSee('Due Soon')
            ->assertSee('Needed Nov 12');
    }

    public function test_the_officer_sees_the_date_needed_and_the_badge(): void
    {
        $this->ticket('2026-11-05');

        $this->actingAs($this->officer)->get('/officer/tickets')
            ->assertSee('Needed by Nov 5, 2026')
            ->assertSee('Overdue');

        $this->actingAs($this->officer)->get('/officer/dashboard')
            ->assertSee('Needed by: Nov 5, 2026')
            ->assertSee('Overdue');
    }

    public function test_a_finished_ticket_keeps_its_date_but_loses_the_badge(): void
    {
        $this->ticket('2026-11-05', Document::APPROVED);

        $this->actingAs($this->officer)->get('/officer/tickets')
            ->assertSee('Needed by Nov 5, 2026')
            ->assertDontSee('Overdue');

        $this->actingAs($this->officer)->get('/officer/dashboard')
            ->assertSee('Needed by: Nov 5, 2026')
            ->assertDontSee('Overdue');
    }

    public function test_tickets_without_a_date_show_no_date_line(): void
    {
        $this->ticket(null);

        $this->actingAs($this->officer)->get('/officer/tickets')->assertOk()->assertDontSee('Needed by');
        $this->actingAs($this->adviser)->get('/signatory/review')->assertOk()->assertDontSee('Needed');
    }

    /** One ticket with one paper at the Adviser's step, filed a minute after the previous one. */
    private function ticket(?string $dateNeeded, string $status = Document::PENDING, string $title = 'DIGITS Week'): Ticket
    {
        $this->travel(1)->minutes();
        $this->refs++;

        $ticket = new Ticket(['ref_no' => "REF-{$this->refs}", 'title' => $title, 'date_needed' => $dateNeeded]);
        $ticket->code = Ticket::nextCode();
        $ticket->organization()->associate($this->digits);
        $ticket->officer()->associate($this->officer);
        $ticket->save();

        $paper = $ticket->documents()->create(['type' => 'Resolution', 'title' => 'Program Flow']);
        $paper->forceFill(['status' => $status])->save();

        return $ticket->fresh();
    }
}
