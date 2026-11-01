<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Live updates (AUD-050): the officer's pages carry a fingerprint, and GET /officer/live
 * says when it changed, so docmon-live.js knows when to refresh the page's live boxes.
 */
class LiveUpdatesTest extends TestCase
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
        $this->adviser = $this->makeUser('signatory', 'EMP-1', ['availability' => 'Available']);
        $this->vpsd = $this->makeUser('signatory', 'EMP-2');
        $this->digits->signatories()->attach($this->adviser, ['step' => 1, 'title' => 'Adviser']);
        $this->digits->signatories()->attach($this->vpsd, ['step' => 2, 'title' => 'VPSD']);
        $this->officer = $this->makeUser('officer', '2201234', ['organization_id' => $this->digits->id, 'position' => 'Secretary']);
    }

    public function test_the_fingerprint_stays_the_same_when_nothing_changed(): void
    {
        $this->ticket('REF-1');

        $this->assertSame($this->stamp(), $this->stamp());
    }

    public function test_a_signatory_changing_availability_changes_the_fingerprint(): void
    {
        $before = $this->stamp();

        // Only the status changes. No back_at field at all: this used to be a 500 (undefined array key)
        $this->actingAs($this->adviser)->put('/signatory/availability', ['availability' => 'In Class'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $afterStatus = $this->stamp();
        $this->assertNotSame($before, $afterStatus);

        // Only the back-at time changes
        $this->actingAs($this->adviser)->put('/signatory/availability', ['availability' => 'In Class', 'back_at' => '3:00 PM']);
        $this->assertNotSame($afterStatus, $this->stamp());
    }

    public function test_a_signatory_acting_on_my_paper_changes_the_fingerprint(): void
    {
        $ticket = $this->ticket('REF-1');
        $before = $this->stamp();

        $this->actingAs($this->adviser)->post("/signatory/documents/{$ticket->documents[0]->id}/action", ['action' => 'approve']);
        $afterApprove = $this->stamp();
        $this->assertNotSame($before, $afterApprove);

        // A second action in the same second still counts (exact values, not only timestamps)
        $this->actingAs($this->vpsd)->post("/signatory/documents/{$ticket->documents[0]->id}/action", ['action' => 'return', 'remarks' => 'Fix the date']);
        $this->assertNotSame($afterApprove, $this->stamp());
    }

    public function test_a_route_change_by_the_admin_changes_the_fingerprint(): void
    {
        $before = $this->stamp();

        $this->digits->signatories()->updateExistingPivot($this->vpsd->id, ['title' => 'VP for Student Development']);

        $this->assertNotSame($before, $this->stamp());
    }

    public function test_other_officers_papers_do_not_change_my_fingerprint(): void
    {
        $before = $this->stamp();

        $other = $this->makeUser('officer', '2209999', ['organization_id' => $this->digits->id]);
        $this->ticket('REF-9', $other);

        $this->assertSame($before, $this->stamp());
    }

    public function test_the_pages_carry_the_same_fingerprint_and_mark_their_live_boxes(): void
    {
        $this->ticket('REF-1');
        $stamp = $this->stamp();

        $pages = [
            '/officer/dashboard' => ['data-live="hero"', 'data-live="stats"', 'data-live="columns"'],
            '/officer/tickets' => ['data-live="tickets"', 'data-live-id='],
            '/officer/tickets/new' => ['data-live="route"'],
            '/officer/signatories' => ['data-live="signatories"'],
        ];

        foreach ($pages as $url => $boxes) {
            $response = $this->actingAs($this->officer)->get($url)
                ->assertOk()
                ->assertSee('<meta name="docmon-live" content="'.url('/officer/live').'" data-stamp="'.$stamp.'">', false)
                ->assertSee('js/docmon-live.js', false);
            foreach ($boxes as $box) {
                $response->assertSee($box, false);
            }
        }

        // The Profile page has nothing others can change: no polling there
        $this->actingAs($this->officer)->get('/officer/profile')->assertDontSee('docmon-live', false);
    }

    public function test_only_officers_can_ask_and_the_answer_is_never_cached(): void
    {
        $this->actingAs($this->officer)->get('/officer/live')
            ->assertOk()
            ->assertJsonStructure(['stamp'])
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->actingAs($this->adviser)->get('/officer/live')->assertForbidden();

        auth()->logout();
        $this->getJson('/officer/live')->assertUnauthorized();
    }

    private function stamp(): string
    {
        return $this->actingAs($this->officer)->getJson('/officer/live')->assertOk()->json('stamp');
    }

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
