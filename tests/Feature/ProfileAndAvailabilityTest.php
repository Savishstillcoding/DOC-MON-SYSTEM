<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The officer's Profile forms and the signatory's availability form (AUD-018).
 */
class ProfileAndAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    // ---------- Profile: Save Changes ----------

    public function test_an_officer_updates_position_email_and_phone(): void
    {
        $officer = $this->makeUser('officer', '2201234');

        $this->actingAs($officer)
            ->put('/officer/profile', ['position' => 'Deputy Secretary', 'email' => 'new@example.com', 'phone' => '09171234567'])
            ->assertRedirect()
            ->assertSessionHas('status', 'Profile saved.');

        $officer->refresh();
        $this->assertSame('Deputy Secretary', $officer->position);
        $this->assertSame('new@example.com', $officer->email);
        $this->assertSame('09171234567', $officer->phone);
    }

    public function test_name_student_id_role_and_status_cannot_be_changed_from_the_profile(): void
    {
        $officer = $this->makeUser('officer', '2201234', ['first_name' => 'MJ', 'status' => 'verified']);

        $this->actingAs($officer)->put('/officer/profile', [
            'position' => 'Secretary', 'email' => $officer->email,
            'first_name' => 'Hacker', 'login_id' => '9999999', 'student_id' => '9999999',
            'role' => 'admin', 'status' => 'rejected',
        ]);

        $officer->refresh();
        $this->assertSame('MJ', $officer->first_name);
        $this->assertSame('2201234', $officer->login_id);
        $this->assertSame('officer', $officer->role);
        $this->assertSame('verified', $officer->status);
    }

    public function test_profile_values_are_checked(): void
    {
        $taken = $this->makeUser('officer', '2205555');
        $officer = $this->makeUser('officer', '2201234');

        $this->actingAs($officer)
            ->put('/officer/profile', ['position' => 'President', 'email' => $taken->email, 'phone' => '12345'])
            ->assertSessionHasErrors([
                'position',
                'email' => 'Another account already uses this email.',
                'phone' => 'Enter an 11-digit number starting with 09.',
            ]);
    }

    public function test_the_profile_page_shows_the_logged_in_officer(): void
    {
        $officer = $this->makeUser('officer', '2201234', ['first_name' => 'MJ Bryan', 'last_name' => 'Labastida']);

        $this->actingAs($officer)->get('/officer/profile')
            ->assertOk()
            ->assertSee('Labastida, MJ Bryan')
            ->assertSee('2201234')
            ->assertDontSee('name="telegram"', false);
    }

    // ---------- Profile: Update Password ----------

    public function test_an_officer_changes_their_password(): void
    {
        $officer = $this->makeUser('officer', '2201234');

        $this->actingAs($officer)
            ->put('/officer/profile/password', [
                'current_password' => 'password',
                'password' => 'a-new-password',
                'password_confirmation' => 'a-new-password',
            ])
            ->assertSessionHas('status', 'Password updated.');

        $this->assertTrue(Hash::check('a-new-password', $officer->fresh()->password));
    }

    public function test_a_wrong_current_password_or_mismatched_confirmation_is_rejected(): void
    {
        $officer = $this->makeUser('officer', '2201234');

        $this->actingAs($officer)
            ->put('/officer/profile/password', [
                'current_password' => 'wrong',
                'password' => 'a-new-password',
                'password_confirmation' => 'something-else',
            ])
            ->assertSessionHasErrorsIn('password', [
                'current_password' => 'Your current password is wrong.',
                'password',
            ]);

        $this->assertTrue(Hash::check('password', $officer->fresh()->password));
    }

    public function test_password_changes_are_rate_limited(): void
    {
        $officer = $this->makeUser('officer', '2201234');
        $guess = ['current_password' => 'guess', 'password' => 'a-new-password', 'password_confirmation' => 'a-new-password'];

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($officer)->put('/officer/profile/password', $guess);
        }

        $this->actingAs($officer)->put('/officer/profile/password', $guess)->assertStatus(429);
    }

    // ---------- Signatory availability ----------

    public function test_a_signatory_updates_their_availability(): void
    {
        $signatory = $this->makeUser('signatory', 'EMP-1');

        $this->actingAs($signatory)
            ->put('/signatory/availability', ['availability' => 'In a Meeting', 'back_at' => '2:00 PM'])
            ->assertSessionHas('status', 'Status updated.');

        $signatory->refresh();
        $this->assertSame('In a Meeting', $signatory->availability);
        $this->assertSame('2:00 PM', $signatory->back_at);

        $this->actingAs($signatory)->get('/signatory/dashboard')->assertSee('In a Meeting · 2:00 PM');
    }

    public function test_choosing_available_clears_the_back_at_time(): void
    {
        $signatory = $this->makeUser('signatory', 'EMP-1', ['availability' => 'In Class', 'back_at' => '3:00 PM']);

        $this->actingAs($signatory)->put('/signatory/availability', ['availability' => 'Available', 'back_at' => '3:00 PM']);

        $this->assertNull($signatory->fresh()->back_at);
    }

    public function test_only_listed_availability_values_are_accepted(): void
    {
        $signatory = $this->makeUser('signatory', 'EMP-1');

        $this->actingAs($signatory)
            ->put('/signatory/availability', ['availability' => 'On Vacation'])
            ->assertSessionHasErrors('availability');
    }

    public function test_officers_cannot_change_signatory_availability(): void
    {
        $this->actingAs($this->makeUser('officer', '2201234'))
            ->put('/signatory/availability', ['availability' => 'Available'])
            ->assertForbidden();
    }

    // ---------- Helper ----------

    private function makeUser(string $role, string $loginId, array $extra = []): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'verified', 'login_id' => $loginId] + $extra);
    }
}
