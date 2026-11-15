<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * AUD-084: each paper on My Tickets has a History fold-out with its whole story, oldest
 * first (submitted, every approve / return / reject / resubmit with who, when and the
 * remark, then where it is now), loaded without one query per paper.
 */
class TimelineTest extends TestCase
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
        $this->vpsd = $this->makeUser('signatory', 'EMP-2', ['first_name' => 'Ramon', 'last_name' => 'Cruz']);
        $this->digits->signatories()->attach($this->adviser, ['step' => 1, 'title' => 'Adviser']);
        $this->digits->signatories()->attach($this->vpsd, ['step' => 2, 'title' => 'VPSD']);
        $this->officer = $this->makeUser('officer', '2201234', ['organization_id' => $this->digits->id,
            'first_name' => 'MJ', 'last_name' => 'Labastida']);
    }

    public function test_the_history_tells_the_whole_story_in_order(): void
    {
        $paper = $this->ticket('REF-1')->documents[0];
        $this->act($this->adviser, $paper, 'return', 'Add the venue.');
        $this->actingAs($this->officer)->post("/officer/documents/{$paper->id}/resubmit");
        $this->act($this->adviser, $paper, 'approve');
        $this->act($this->vpsd, $paper, 'approve');
        $this->flushSession();

        $this->actingAs($this->officer)->get('/officer/tickets')
            ->assertSee('History (5)')
            ->assertSeeInOrder([
                'Submitted', 'Labastida, MJ',
                'Returned for revision by Adviser', 'Dela Cruz, Ana', 'Add the venue.',
                'Resubmitted to Adviser', 'Labastida, MJ',
                'Approved by Adviser', 'Dela Cruz, Ana',
                'Approved by VPSD (final signature)', 'Cruz, Ramon',
            ])
            ->assertDontSee('Now waiting for');
    }

    public function test_an_unfinished_paper_ends_with_where_it_is_now(): void
    {
        $paper = $this->ticket('REF-1')->documents[0];
        $this->act($this->adviser, $paper, 'approve');
        $this->flushSession();

        $this->actingAs($this->officer)->get('/officer/tickets')
            ->assertSeeInOrder(['History (3)', 'Submitted', 'Approved by Adviser', 'Now waiting for VPSD to sign', 'Cruz, Ramon']);

        $this->act($this->vpsd, $paper->fresh(), 'return', 'Wrong date');
        $this->flushSession();

        $this->actingAs($this->officer)->get('/officer/tickets')
            ->assertSeeInOrder(['Returned for revision by VPSD', 'Wrong date', 'Now with you: revise it, then resubmit it to VPSD']);
    }

    public function test_a_rejected_paper_stops_at_the_rejection(): void
    {
        $paper = $this->ticket('REF-1')->documents[0];
        $this->act($this->adviser, $paper, 'reject', 'Not allowed this term');
        $this->flushSession();

        $this->actingAs($this->officer)->get('/officer/tickets')
            ->assertSeeInOrder(['History (2)', 'Rejected by Adviser', 'Not allowed this term'])
            ->assertDontSee('Now waiting for')
            ->assertDontSee('Now with you');
    }

    public function test_the_history_does_not_cost_a_query_per_paper(): void
    {
        $count = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            // fresh(): a new user object each time, like a real request (a reused one keeps
            // its organization cached and makes the second page look one query cheaper)
            $this->actingAs($this->officer->fresh())->get('/officer/tickets')->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        $first = $this->ticket('REF-1')->documents[0];
        $this->act($this->adviser, $first, 'approve');
        $this->flushSession();
        $one = $count();

        foreach (range(2, 6) as $i) {
            $this->act($this->adviser, $this->ticket("REF-{$i}")->documents[0], 'return', 'Fix it');
        }
        $this->flushSession();

        $this->assertSame($one, $count(), 'More papers must not mean more queries (N+1)');
    }

    // ---------- helpers ----------

    private function ticket(string $refNo): Ticket
    {
        $ticket = new Ticket(['ref_no' => $refNo, 'title' => 'DIGITS Week']);
        $ticket->code = Ticket::nextCode();
        $ticket->organization()->associate($this->digits);
        $ticket->officer()->associate($this->officer);
        $ticket->save();
        $ticket->documents()->create(['type' => 'Resolution', 'title' => 'Program Flow']);

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
