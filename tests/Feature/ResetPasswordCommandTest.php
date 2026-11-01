<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * php artisan docmon:reset-password: a forgotten password can only be replaced, never read.
 */
class ResetPasswordCommandTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'verified', 'login_id' => 'admin']);
    }

    public function test_it_sets_a_new_password_after_confirming(): void
    {
        $this->artisan('docmon:reset-password', ['login_id' => 'admin'])
            ->expectsConfirmation('Set a new password for this account?', 'yes')
            ->expectsQuestion('New password (at least 8 characters)', 'NewAdmin#2026')
            ->expectsQuestion('Type it again', 'NewAdmin#2026')
            ->expectsOutputToContain('can log in with the new password')
            ->assertSuccessful();

        $hash = $this->admin->fresh()->password;
        $this->assertTrue(Hash::check('NewAdmin#2026', $hash));
        $this->assertStringStartsWith('$2y$', $hash);   // stored as a bcrypt hash, never as typed (tests use a lower cost to run fast)
    }

    public function test_saying_no_or_mistyping_changes_nothing(): void
    {
        $this->artisan('docmon:reset-password', ['login_id' => 'admin'])
            ->expectsConfirmation('Set a new password for this account?', 'no')
            ->assertSuccessful();

        $this->artisan('docmon:reset-password', ['login_id' => 'admin'])
            ->expectsConfirmation('Set a new password for this account?', 'yes')
            ->expectsQuestion('New password (at least 8 characters)', 'NewAdmin#2026')
            ->expectsQuestion('Type it again', 'NewAdmin#2027')
            ->expectsOutputToContain('different')
            ->assertFailed();

        $this->assertTrue(Hash::check('password', $this->admin->fresh()->password));
    }

    public function test_an_unknown_account_is_refused(): void
    {
        $this->artisan('docmon:reset-password', ['login_id' => 'nobody'])
            ->expectsOutputToContain('No account')
            ->assertFailed();
    }
}
