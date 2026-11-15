<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The availability options match Chapter 1, Objective 3 (Available, In a Meeting, In Class,
 * On Lunch Break, On Leave), plus Out of Office. One list (User::AVAILABILITY) drives the
 * form, the validation and the badge colours, so they can't disagree.
 */
class AvailabilityOptionsTest extends TestCase
{
    use RefreshDatabase;

    private const OBJECTIVE_3 = ['Available', 'In a Meeting', 'In Class', 'On Lunch Break', 'On Leave'];

    public function test_every_option_promised_in_objective_3_exists(): void
    {
        foreach (self::OBJECTIVE_3 as $option) {
            $this->assertArrayHasKey($option, User::AVAILABILITY, "Objective 3 promises \"{$option}\"");
        }
        $this->assertArrayHasKey('Out of Office', User::AVAILABILITY);
    }

    public function test_the_signatory_form_offers_every_option_in_order(): void
    {
        $signatory = $this->makeUser('signatory', 'EMP-1');

        $this->actingAs($signatory)->get('/signatory/dashboard')->assertSeeInOrder(
            array_map(fn ($o) => 'value="'.$o.'"', array_keys(User::AVAILABILITY)), false);
    }

    public function test_each_option_can_be_saved_and_is_shown_in_its_colour(): void
    {
        $digits = Organization::create(['code' => 'DIGITS', 'name' => 'DIGITS']);
        $adviser = $this->makeUser('signatory', 'EMP-1');
        $digits->signatories()->attach($adviser, ['step' => 1, 'title' => 'Adviser']);
        $officer = $this->makeUser('officer', '2201234', ['organization_id' => $digits->id]);

        $expected = ['On Lunch Break' => 'busy', 'On Leave' => 'away', 'Out of Office' => 'away', 'In Class' => 'busy', 'Available' => 'available'];
        foreach ($expected as $option => $colour) {
            $this->actingAs($adviser)->put('/signatory/availability', ['availability' => $option, 'back_at' => '1:00 PM'])
                ->assertSessionHasNoErrors();
            $this->assertSame($option, $adviser->fresh()->availability);

            // The officer's route rail shows it with the right dot colour
            $this->actingAs($officer)->get('/officer/dashboard')
                ->assertSee('dm-availability dm-availability-'.$colour, false)
                ->assertSee($option);
        }
    }

    public function test_anything_else_is_refused(): void
    {
        $this->actingAs($this->makeUser('signatory', 'EMP-1'))
            ->put('/signatory/availability', ['availability' => 'On Vacation'])
            ->assertSessionHasErrors('availability');
    }

    private function makeUser(string $role, string $loginId, array $extra = []): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'verified', 'login_id' => $loginId] + $extra);
    }
}
