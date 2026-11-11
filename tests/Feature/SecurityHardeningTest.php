<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\IdDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Batch 1 security fixes: password rule (AUD-067), sign out other devices (AUD-068),
 * security headers (AUD-069), log-ins and ID views on record (AUD-071, AUD-072),
 * per-account log-in limit (AUD-073), docmon:create-admin (AUD-091).
 */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->officer = User::factory()->create(['role' => 'officer', 'status' => 'verified', 'login_id' => '2201234']);
    }

    // ---------- AUD-067: password rule ----------

    public function test_passwords_need_letters_and_numbers(): void
    {
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);

        foreach (['password', '12345678', 'short1'] as $weak) {
            $this->changePassword($weak)->assertSessionHasErrorsIn('password', 'password');
        }
        $this->assertTrue(Hash::check('password', $this->officer->fresh()->password));   // unchanged

        $this->changePassword('Bagong2026')->assertSessionHasNoErrors();
    }

    public function test_a_password_from_a_known_data_leak_is_refused(): void
    {
        // The service answers with the hash endings it knows, for the first 5 characters sent
        $hash = strtoupper(sha1('Leaked2026'));
        Http::fake(['api.pwnedpasswords.com/range/'.substr($hash, 0, 5) => Http::response(substr($hash, 5).":4210\r\nABCDEF0123456789ABCDEF0123456789ABC:1")]);

        $this->changePassword('Leaked2026')->assertSessionHasErrorsIn('password', 'password');

        // Only the first 5 characters of the hash left DOC-MON, never the password
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/range/'.substr($hash, 0, 5)));
    }

    public function test_the_password_is_accepted_when_the_leak_check_is_down(): void
    {
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 503)]);

        $this->changePassword('Bagong2026')->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('Bagong2026', $this->officer->fresh()->password));
    }

    // ---------- AUD-068: other devices signed out ----------

    public function test_changing_the_password_signs_out_other_devices_only(): void
    {
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);
        config(['session.driver' => 'database']);   // what the live site uses
        $someoneElse = User::factory()->create(['role' => 'officer', 'status' => 'verified', 'login_id' => '2205678']);
        foreach ([['phone', $this->officer], ['laptop', $this->officer], ['theirs', $someoneElse]] as [$id, $user]) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        }

        $this->changePassword('Bagong2026')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Password updated. Any other device using your account was signed out.');

        $left = DB::table('sessions')->pluck('id');
        $this->assertNotContains('phone', $left);
        $this->assertNotContains('laptop', $left);
        $this->assertContains('theirs', $left);   // other people's sessions are untouched
        $this->assertAuthenticatedAs($this->officer);   // this browser stays logged in
    }

    // ---------- AUD-069: security headers ----------

    public function test_pages_send_the_security_headers(): void
    {
        foreach (['/login', '/privacy'] as $page) {
            $this->get($page)
                ->assertHeader('X-Frame-Options', 'DENY')
                ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'")
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
                ->assertHeaderMissing('Strict-Transport-Security');   // only on the live site over HTTPS
        }

        $this->actingAs($this->officer)->get('/officer/dashboard')->assertHeader('X-Frame-Options', 'DENY');
    }

    // ---------- AUD-071: log-ins on record ----------

    public function test_log_ins_are_recorded_without_the_password(): void
    {
        $this->post('/login/officer', ['student_id' => '2201234', 'password' => 'Secret-Guess-1']);
        $this->post('/login/officer', ['student_id' => 'MyPassword99', 'password' => 'x']);   // password typed into the ID box
        $this->post('/login/officer', ['student_id' => '2201234', 'password' => 'password']);

        $logs = ActivityLog::where('category', 'logins')->orderBy('id')->get();
        $this->assertSame(['Failed log-in', 'Failed log-in', 'Logged in'], $logs->pluck('action')->all());
        $this->assertStringContainsString('Wrong password for', $logs[0]->details);
        $this->assertStringContainsString('Unknown ID', $logs[1]->details);
        $this->assertStringContainsString('Student Officer log-in page · IP 127.0.0.1', $logs[2]->details);
        $this->assertSame($this->officer->id, (int) $logs[2]->user_id);

        $all = ActivityLog::pluck('details')->implode(' ');
        $this->assertStringNotContainsString('Secret-Guess-1', $all);
        $this->assertStringNotContainsString('MyPassword99', $all);
    }

    public function test_a_blocked_log_in_is_recorded(): void
    {
        User::factory()->create(['role' => 'officer', 'status' => 'pending', 'login_id' => '2209876']);

        $this->post('/login/officer', ['student_id' => '2209876', 'password' => 'password']);

        $this->assertGuest();
        $log = ActivityLog::where('category', 'logins')->sole();
        $this->assertSame('Blocked log-in', $log->action);
        $this->assertStringContainsString('Unverified', $log->details);
    }

    // ---------- AUD-072: ID views on record ----------

    public function test_viewing_an_id_is_recorded(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'verified', 'login_id' => 'admin']);
        $pending = User::factory()->create(['role' => 'officer', 'status' => 'pending', 'login_id' => '2209876']);
        $pending->idDocument()->save((new IdDocument)->forceFill(['mime' => 'image/png', 'size' => 4, 'data' => "\x89PNG"]));

        $this->actingAs($admin)->get("/admin/users/{$pending->id}/id-document")
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');

        $log = ActivityLog::where('action', 'Viewed ID')->sole();
        $this->assertSame($admin->id, (int) $log->user_id);
        $this->assertStringContainsString("({$pending->login_id})", $log->details);
    }

    // ---------- AUD-073: per-account log-in limit ----------

    public function test_switching_ip_addresses_does_not_allow_endless_guessing(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])
                ->post('/login/officer', ['student_id' => '2201234', 'password' => "guess-{$i}"])
                ->assertRedirect();
        }

        // 11th try on the same account, from yet another address: stopped
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])
            ->post('/login/officer', ['student_id' => '2201234', 'password' => 'guess-11'])
            ->assertStatus(429);

        // Another account isn't affected, and the lock lasts only a minute
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.98'])
            ->post('/login/officer', ['student_id' => '2205678', 'password' => 'x'])
            ->assertRedirect();

        $this->travel(61)->seconds();
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.97'])
            ->post('/login/officer', ['student_id' => '2201234', 'password' => 'password'])
            ->assertRedirect('/officer/dashboard');
    }

    // ---------- AUD-091: docmon:create-admin ----------

    public function test_the_command_creates_an_admin_who_can_log_in(): void
    {
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);

        $this->createAdmin()
            ->expectsConfirmation('Create this admin account?', 'yes')
            ->expectsQuestion('Password (8+ characters, letters and numbers)', 'Admin2026x')
            ->expectsQuestion('Type it again', 'Admin2026x')
            ->expectsOutputToContain('can log in at /login/admin as admin2')
            ->assertSuccessful();

        $admin = User::where('login_id', 'admin2')->sole();
        $this->assertSame(['admin', 'verified'], [$admin->role, $admin->status]);
        $this->assertNull($admin->privacy_accepted_at);   // accepts the notice at first log-in
        $this->assertNotSame('Admin2026x', $admin->password);
        $this->assertTrue(ActivityLog::where('action', 'Admin account created')->exists());

        $this->post('/login/admin', ['username' => 'admin2', 'password' => 'Admin2026x'])->assertRedirect('/admin/dashboard');
    }

    public function test_saying_no_or_mistyping_creates_nothing(): void
    {
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);

        $this->createAdmin()->expectsConfirmation('Create this admin account?', 'no')->assertSuccessful();

        $this->createAdmin()
            ->expectsConfirmation('Create this admin account?', 'yes')
            ->expectsQuestion('Password (8+ characters, letters and numbers)', 'Admin2026x')
            ->expectsQuestion('Type it again', 'Admin2026y')
            ->expectsOutputToContain('different')
            ->assertFailed();

        $this->assertFalse(User::where('login_id', 'admin2')->exists());
    }

    // ---------- Helpers ----------

    private function changePassword(string $new)
    {
        return $this->actingAs($this->officer)->put('/officer/profile/password', [
            'current_password' => 'password', 'password' => $new, 'password_confirmation' => $new,
        ]);
    }

    private function createAdmin()
    {
        return $this->artisan('docmon:create-admin')
            ->expectsQuestion('First name', 'Juan')
            ->expectsQuestion('Last name', 'Carusa')
            ->expectsQuestion('Username (for /login/admin)', 'admin2')
            ->expectsQuestion('Email (for password reset)', 'juan@example.com');
    }
}
