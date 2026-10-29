<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Real log-in, log-out and role protection (AUD-017).
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    /** role => [log-in form's ID field, a login ID] */
    public static function roles(): array
    {
        return [
            'officer' => ['officer', 'student_id', '2201234'],
            'signatory' => ['signatory', 'employee_id', 'EMP-0451'],
            'admin' => ['admin', 'username', 'admin'],
        ];
    }

    public static function dashboardPages(): array
    {
        return [
            ['/officer/dashboard'], ['/officer/tickets'], ['/officer/tickets/new'],
            ['/officer/signatories'], ['/officer/profile'],
            ['/signatory/dashboard'], ['/admin/dashboard'],
        ];
    }

    // ---------- Guests ----------

    #[DataProvider('dashboardPages')]
    public function test_guests_are_sent_to_log_in_from_every_dashboard_page(string $page): void
    {
        $this->get($page)->assertRedirect('/login');
    }

    // ---------- Logging in ----------

    #[DataProvider('roles')]
    public function test_a_verified_user_logs_in_and_lands_on_their_dashboard(string $role, string $idField, string $loginId): void
    {
        $user = $this->makeUser($role, $loginId);

        $this->post("/login/{$role}", [$idField => $loginId, 'password' => 'password'])
            ->assertRedirect("/{$role}/dashboard");

        $this->assertAuthenticatedAs($user);
        $this->get("/{$role}/dashboard")->assertOk();
    }

    public function test_a_wrong_password_is_rejected(): void
    {
        $this->makeUser('officer', '2201234');

        $this->from('/login/officer')
            ->post('/login/officer', ['student_id' => '2201234', 'password' => 'wrong-password'])
            ->assertRedirect('/login/officer')
            ->assertSessionHasErrors(['student_id' => 'Wrong ID or password.']);

        $this->assertGuest();
    }

    public function test_an_unknown_id_gets_the_same_message_as_a_wrong_password(): void
    {
        $this->post('/login/officer', ['student_id' => '9999999', 'password' => 'password'])
            ->assertSessionHasErrors(['student_id' => 'Wrong ID or password.']);

        $this->assertGuest();
    }

    public function test_a_pending_account_cannot_log_in_yet(): void
    {
        $this->makeUser('officer', '2209876', status: 'pending');

        $this->post('/login/officer', ['student_id' => '2209876', 'password' => 'password'])
            ->assertSessionHasErrors(['student_id' => 'Your account is waiting for admin verification.']);

        $this->assertGuest();
    }

    public function test_a_rejected_account_cannot_log_in(): void
    {
        $this->makeUser('officer', '2201111', status: 'rejected');

        $this->post('/login/officer', ['student_id' => '2201111', 'password' => 'password'])
            ->assertSessionHasErrors('student_id');

        $this->assertGuest();
    }

    public function test_an_officer_cannot_log_in_through_the_admin_form(): void
    {
        $this->makeUser('officer', '2201234');

        $this->post('/login/admin', ['username' => '2201234', 'password' => 'password'])
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_the_id_and_password_are_required(): void
    {
        $this->post('/login/officer', [])->assertSessionHasErrors(['student_id', 'password']);
    }

    public function test_log_in_attempts_are_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login/officer', ['student_id' => '2201234', 'password' => 'guess-'.$i]);
        }

        // 6th attempt within a minute: "Too Many Requests"
        $this->post('/login/officer', ['student_id' => '2201234', 'password' => 'guess-6'])->assertStatus(429);
    }

    // ---------- Role protection ----------

    public function test_an_officer_cannot_open_signatory_or_admin_pages(): void
    {
        $this->actingAs($this->makeUser('officer', '2201234'));

        $this->get('/signatory/dashboard')->assertForbidden();
        $this->get('/admin/dashboard')->assertForbidden();
        $this->get('/officer/dashboard')->assertOk();
    }

    public function test_a_signatory_cannot_open_officer_or_admin_pages(): void
    {
        $this->actingAs($this->makeUser('signatory', 'EMP-0451'));

        $this->get('/officer/tickets/new')->assertForbidden();
        $this->get('/admin/dashboard')->assertForbidden();
    }

    public function test_a_logged_in_user_opening_a_log_in_page_goes_to_their_dashboard(): void
    {
        $this->actingAs($this->makeUser('signatory', 'EMP-0451'));

        $this->get('/login/officer')->assertRedirect('/signatory/dashboard');
    }

    // ---------- Logging out ----------

    public function test_logging_out_ends_the_session(): void
    {
        $this->actingAs($this->makeUser('officer', '2201234'));

        $this->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
        $this->get('/officer/dashboard')->assertRedirect('/login');
    }

    // ---------- Public pages ----------

    public function test_sign_up_offers_no_admin_option(): void
    {
        $this->get('/signup')
            ->assertOk()
            ->assertDontSee('/register/admin');

        $this->get('/register/admin')->assertNotFound();
    }

    public function test_the_developer_components_page_is_hidden_outside_local(): void
    {
        // Tests run with APP_ENV=testing, like the live site runs with production
        $this->get('/dev/components')->assertNotFound();
    }

    // ---------- Demo data ----------

    public function test_the_seeded_demo_accounts_can_log_in(): void
    {
        $this->seed();

        $this->post('/login/officer', ['student_id' => '2201234', 'password' => 'password'])
            ->assertRedirect('/officer/dashboard');
    }

    // ---------- Helper ----------

    private function makeUser(string $role, string $loginId, string $status = 'verified'): User
    {
        // Factories may set role and status (they're protected from forms, not from factories)
        return User::factory()->create(['role' => $role, 'status' => $status, 'login_id' => $loginId]);
    }
}
