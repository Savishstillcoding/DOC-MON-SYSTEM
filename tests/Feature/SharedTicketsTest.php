<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentAction;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use App\Support\LiveStamp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AUD-075: an organization's officers share its tickets, so the Deputy can carry on
 * when the Secretary is away or has graduated. The filer stays on record, the history
 * says who did what, and other organizations still see nothing.
 */
class SharedTicketsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $digits;

    private User $secretary;

    private User $deputy;

    private User $outsider;   // an officer of another organization

    private User $adviser;

    private Ticket $ticket;

    private Document $paper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->digits = Organization::create(['code' => 'DIGITS', 'name' => 'DIGITS']);
        $this->adviser = $this->makeUser('signatory', 'EMP-1', ['first_name' => 'Ana', 'last_name' => 'Dela Cruz']);
        $this->digits->signatories()->attach($this->adviser, ['step' => 1, 'title' => 'Adviser']);
        $this->secretary = $this->officer('2201234', $this->digits, 'Labastida', 'Secretary');
        $this->deputy = $this->officer('2205678', $this->digits, 'Santos', 'Deputy Secretary');
        $this->outsider = $this->officer('2209999', Organization::create(['code' => 'OTHER', 'name' => 'Other Org']), 'Villanueva', 'Secretary');

        $this->ticket = new Ticket(['ref_no' => 'REF-1', 'title' => 'DIGITS Week']);
        $this->ticket->code = Ticket::nextCode();
        $this->ticket->organization()->associate($this->digits);
        $this->ticket->officer()->associate($this->secretary);
        $this->ticket->save();
        $this->paper = $this->ticket->documents()->create(['type' => 'Resolution', 'title' => 'Program Flow']);
    }

    public function test_the_deputy_sees_the_secretarys_ticket_and_who_filed_it(): void
    {
        $this->actingAs($this->deputy)->get('/officer/tickets')
            ->assertSee($this->ticket->code)
            ->assertSee('Filed by Labastida, MJ');

        $this->actingAs($this->deputy)->get('/officer/dashboard')
            ->assertViewHas('stats', fn ($s) => $s['total'] === 1 && $s['pending'] === 1)
            ->assertSee('Filed by: Labastida, MJ');

        $this->actingAs($this->deputy)->get('/officer/signatories')
            ->assertViewHas('signatories', fn ($list) => $list->sole()->pending === 1);
    }

    public function test_the_deputy_can_resubmit_and_the_history_says_who_did(): void
    {
        $this->return();

        $this->actingAs($this->deputy)->post("/officer/documents/{$this->paper->id}/resubmit")->assertSessionHasNoErrors();

        $this->assertSame(Document::PENDING, $this->paper->fresh()->status);
        $action = DocumentAction::where('action', DocumentAction::RESUBMITTED)->sole();
        $this->assertTrue($action->user->is($this->deputy));
        $this->assertTrue($this->ticket->fresh()->officer->is($this->secretary));   // still filed by the Secretary

        $this->actingAs($this->secretary)->get('/officer/dashboard')
            ->assertSee('resubmitted to Adviser by Santos, MJ');
    }

    public function test_a_paper_survives_its_filer_leaving(): void
    {
        // The real problem: the Secretary graduates with a returned paper
        $this->return();
        $this->secretary->forceFill(['status' => 'deactivated'])->save();

        $this->actingAs($this->deputy)->get('/officer/tickets')->assertSee('Filed by Labastida, MJ');
        $this->actingAs($this->deputy)->post("/officer/documents/{$this->paper->id}/resubmit")->assertSessionHasNoErrors();

        $this->assertSame(Document::PENDING, $this->paper->fresh()->status);
    }

    public function test_another_organization_sees_and_touches_nothing(): void
    {
        $this->return();

        $this->actingAs($this->outsider)->get('/officer/tickets')->assertDontSee($this->ticket->code);
        $this->actingAs($this->outsider)->get('/officer/tickets?q='.urlencode($this->ticket->code))->assertDontSee('DIGITS Week');
        $this->actingAs($this->outsider)->get('/officer/dashboard')
            ->assertViewHas('stats', fn ($s) => $s['total'] === 0)
            ->assertDontSee($this->ticket->code);
        $this->actingAs($this->outsider)->get('/officer/signatories')->assertDontSee('Dela Cruz, Ana');

        $this->actingAs($this->outsider)->post("/officer/documents/{$this->paper->id}/resubmit")->assertSessionHasErrors('resubmit');
        $this->assertSame(Document::RETURNED, $this->paper->fresh()->status);
    }

    public function test_an_officer_moved_to_another_organization_leaves_its_tickets_behind(): void
    {
        $this->return();
        $this->deputy->forceFill(['organization_id' => $this->outsider->organization_id])->save();
        $this->deputy->unsetRelation('organization');

        $this->actingAs($this->deputy)->get('/officer/tickets')->assertDontSee($this->ticket->code);
        $this->actingAs($this->deputy)->post("/officer/documents/{$this->paper->id}/resubmit")->assertSessionHasErrors('resubmit');
    }

    public function test_an_officer_without_an_organization_sees_nothing(): void
    {
        $this->secretary->forceFill(['organization_id' => null])->save();

        $this->actingAs($this->secretary)->get('/officer/tickets')->assertOk()->assertDontSee($this->ticket->code);
        $this->assertFalse($this->ticket->fresh()->isVisibleTo($this->secretary));
    }

    public function test_live_updates_follow_the_organization(): void
    {
        $before = LiveStamp::officer($this->deputy);

        // A signatory acts on the Secretary's ticket: the Deputy's page refreshes
        $this->return();

        $this->assertNotSame($before, LiveStamp::officer($this->deputy));
    }

    private function return(): void
    {
        $this->actingAs($this->adviser)->post("/signatory/documents/{$this->paper->id}/action", ['action' => 'return', 'remarks' => 'Fix the date']);
        // Tests share one session: without this, the Adviser's green message shows on the next person's page
        $this->flushSession();
    }

    private function officer(string $loginId, Organization $organization, string $lastName, string $position): User
    {
        return $this->makeUser('officer', $loginId, [
            'organization_id' => $organization->id, 'first_name' => 'MJ', 'last_name' => $lastName, 'position' => $position,
        ]);
    }

    private function makeUser(string $role, string $loginId, array $extra = []): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'verified', 'login_id' => $loginId] + $extra);
    }
}
