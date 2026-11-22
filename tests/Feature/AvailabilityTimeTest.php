<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * "Back at 2:00 PM" is free text with no expiry, so every page that shows a
 * signatory's status also says when they set it: "Back at 2:00 PM · set 9:14 AM".
 */
class AvailabilityTimeTest extends TestCase
{
    /** The time keeps "9:14" and "AM" together with a no-break space (U+00A0). */
    private const NBSP = "\u{00A0}";

    use RefreshDatabase;

    private User $adviser;

    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();
        $digits = Organization::create(['code' => 'DIGITS', 'name' => 'DIGITS']);
        $this->adviser = $this->makeUser('signatory', 'EMP-1', ['first_name' => 'Ana', 'last_name' => 'Dela Cruz']);
        $digits->signatories()->attach($this->adviser, ['step' => 1, 'title' => 'Adviser']);
        $this->officer = $this->makeUser('officer', '2201234', ['organization_id' => $digits->id]);

        $ticket = new Ticket(['ref_no' => 'REF-1', 'title' => 'DIGITS Week']);
        $ticket->code = Ticket::nextCode();
        $ticket->organization()->associate($digits);
        $ticket->officer()->associate($this->officer);
        $ticket->save();
        $ticket->documents()->create(['type' => 'Resolution', 'title' => 'Program Flow']);   // waiting for the Adviser
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_officers_see_when_the_status_was_set(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-16 09:14', 'Asia/Manila'));
        $this->setStatus('In a Meeting', '2:00 PM');

        $this->assertTrue($this->adviser->fresh()->availability_updated_at->equalTo(now()));

        // Ticket card, the route rail, New Ticket's route and the Signatories page all say it
        $this->actingAs($this->officer)->get('/officer/dashboard')
            ->assertSee('In a Meeting · Back at 2:00 PM · set 9:14'.self::NBSP.'AM');
        $this->actingAs($this->officer)->get('/officer/tickets/new')
            ->assertSee('In a Meeting · Back at 2:00 PM · set 9:14'.self::NBSP.'AM');
        $this->actingAs($this->officer)->get('/officer/signatories')
            ->assertSeeInOrder(['Status set', '9:14'.self::NBSP.'AM']);
    }

    public function test_an_old_status_says_how_old_it_is(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-16 16:30', 'Asia/Manila'));
        $this->setStatus('Out of Office', 'Tomorrow');

        Carbon::setTestNow(Carbon::parse('2026-11-17 08:00', 'Asia/Manila'));
        $this->actingAs($this->officer)->get('/officer/dashboard')
            ->assertSee('Out of Office · Back at Tomorrow · set yesterday, 4:30'.self::NBSP.'PM');

        Carbon::setTestNow(Carbon::parse('2026-11-20 08:00', 'Asia/Manila'));
        $this->actingAs($this->officer)->get('/officer/dashboard')
            ->assertSee('Out of Office · Back at Tomorrow · set Nov 16');
    }

    public function test_saving_the_same_status_again_counts_as_fresh(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-16 09:00', 'Asia/Manila'));
        $this->setStatus('In Class', '3:00 PM');

        Carbon::setTestNow(Carbon::parse('2026-11-16 13:45', 'Asia/Manila'));
        $this->setStatus('In Class', '3:00 PM');   // "still in class"

        $this->actingAs($this->officer)->get('/officer/dashboard')->assertSee('set 1:45'.self::NBSP.'PM');
    }

    public function test_available_needs_no_note_and_old_data_still_shows_back_at(): void
    {
        $this->setStatus('Available');
        $this->assertNull($this->adviser->fresh()->availabilityNote());

        // Set before this column existed: no time to show, but "Back at" still does
        $this->adviser->forceFill(['availability' => 'In a Meeting', 'back_at' => '2:00 PM', 'availability_updated_at' => null])->save();
        $this->assertSame('Back at 2:00 PM', $this->adviser->fresh()->availabilityNote());
    }

    public function test_the_signatory_sees_when_they_last_set_it(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-16 09:14', 'Asia/Manila'));
        $this->setStatus('In a Meeting', '2:00 PM');

        $this->actingAs($this->adviser)->get('/signatory/dashboard')->assertSee('last set 9:14'.self::NBSP.'AM');
    }

    // ---------- helpers ----------

    private function setStatus(string $availability, ?string $backAt = null): void
    {
        $this->actingAs($this->adviser)
            ->put('/signatory/availability', ['availability' => $availability, 'back_at' => $backAt])
            ->assertSessionHasNoErrors();
    }

    private function makeUser(string $role, string $loginId, array $extra = []): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'verified', 'login_id' => $loginId] + $extra);
    }
}
