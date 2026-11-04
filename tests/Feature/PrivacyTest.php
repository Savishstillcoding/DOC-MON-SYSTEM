<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The privacy notice and consent (AUD-078, Data Privacy Act): a public notice, consent
 * at sign-up, existing accounts asked once, and the admin's yearly review of old accounts.
 */
class PrivacyTest extends TestCase
{
    use RefreshDatabase;

    private Organization $digits;

    protected function setUp(): void
    {
        parent::setUp();

        $this->digits = Organization::create(['code' => 'DIGITS', 'name' => 'DIGITS']);
    }

    // ---------- The notice ----------

    public function test_anyone_can_read_the_notice_and_it_states_the_real_promises(): void
    {
        config(['services.privacy.email' => 'privacy@example.com']);

        $this->get('/privacy')->assertOk()
            ->assertSee('Republic Act No. 10173')
            ->assertSee('up to '.User::RETENTION_YEARS.' years')
            ->assertSee('deleted as soon as the admin approves or rejects')
            ->assertSee('Brevo')
            ->assertSee('Telegram')
            ->assertSee('MJ Bryan T. Labastida')
            ->assertSee('mailto:privacy@example.com', false)
            ->assertDontSee('I have read this and I accept');
    }

    public function test_the_contact_email_stays_off_the_page_until_it_is_set(): void
    {
        $this->get('/privacy')->assertOk()->assertSee('MJ Bryan T. Labastida')->assertDontSee('mailto:', false);
    }

    // ---------- Sign-up ----------

    public function test_sign_up_needs_consent_and_records_when(): void
    {
        $this->post('/register/officer', $this->form(['privacy_accepted' => null]))
            ->assertSessionHasErrors(['privacy_accepted' => 'Please read and accept the Privacy Notice to sign up.']);
        $this->assertSame(0, User::count());

        $this->post('/register/officer', $this->form())->assertSessionHasNoErrors();

        $this->assertNotNull(User::sole()->privacy_accepted_at);
    }

    public function test_a_tampered_form_cannot_fake_the_consent_time(): void
    {
        $this->post('/register/officer', $this->form(['privacy_accepted_at' => '2000-01-01']));

        $this->assertTrue(User::sole()->privacy_accepted_at->isToday());
    }

    public static function signUpPages(): array
    {
        return [['/register/officer'], ['/register/signatory']];
    }

    #[DataProvider('signUpPages')]
    public function test_the_sign_up_forms_show_consent_and_an_optional_phone(string $page): void
    {
        $html = $this->get($page)->assertOk()
            ->assertSee('name="privacy_accepted"', false)
            ->assertSee('href="'.url('/privacy').'" target="_blank"', false)
            ->assertSee('Phone Number (optional)')
            ->getContent();

        preg_match('/<input id="phone"[^>]*>/', $html, $phone);
        $this->assertStringNotContainsString('required', $phone[0]);
    }

    public function test_the_phone_is_optional_but_must_be_valid_when_given(): void
    {
        $this->post('/register/officer', $this->form(['phone' => '']))->assertSessionHasNoErrors();
        $this->assertNull(User::sole()->phone);

        $this->post('/register/officer', $this->form(['student_id' => '2209999', 'email' => 'b@lnu.edu.ph', 'phone' => '12345']))
            ->assertSessionHasErrors('phone');
    }

    // ---------- Existing accounts: asked once ----------

    public static function roles(): array
    {
        return [['officer'], ['signatory'], ['admin']];
    }

    #[DataProvider('roles')]
    public function test_an_existing_account_is_asked_once_then_left_alone(string $role): void
    {
        $user = $this->makeUser($role, ['privacy_accepted_at' => null]);

        $this->actingAs($user)->get("/{$role}/dashboard")->assertRedirect('/privacy');
        $this->actingAs($user)->get('/privacy')->assertSee('I have read this and I accept');

        $this->post('/privacy/accept')->assertRedirect("/{$role}/dashboard");

        $this->assertNotNull($user->fresh()->privacy_accepted_at);
        $this->assertSame('Accepted privacy notice', ActivityLog::latest('id')->first()->action);
        $this->actingAs($user->fresh())->get("/{$role}/dashboard")->assertOk();
        $this->actingAs($user->fresh())->get('/privacy')->assertDontSee('I have read this and I accept');
    }

    public function test_nothing_can_be_changed_before_accepting(): void
    {
        $user = $this->makeUser('officer', ['privacy_accepted_at' => null, 'phone' => null]);

        $this->actingAs($user)->put('/officer/profile', ['position' => 'Secretary', 'email' => 'new@lnu.edu.ph'])
            ->assertRedirect('/privacy');

        $this->assertNotSame('new@lnu.edu.ph', $user->fresh()->email);
    }

    public function test_someone_who_wont_accept_can_still_log_out(): void
    {
        $user = $this->makeUser('officer', ['privacy_accepted_at' => null]);

        $this->actingAs($user)->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_accepting_twice_keeps_the_first_time(): void
    {
        $first = now()->subDay()->startOfSecond();
        $user = $this->makeUser('officer', ['privacy_accepted_at' => $first]);

        $this->actingAs($user)->post('/privacy/accept');

        $this->assertTrue($user->fresh()->privacy_accepted_at->equalTo($first));
    }

    public function test_guests_cannot_accept(): void
    {
        $this->post('/privacy/accept')->assertRedirect('/login');
    }

    // ---------- The admin's yearly review ----------

    public function test_the_admin_sees_active_accounts_past_the_retention_period(): void
    {
        $old = $this->makeUser('officer', ['last_name' => 'Oldaccount', 'created_at' => now()->subYears(User::RETENTION_YEARS)->subDay()]);
        $this->makeUser('officer', ['last_name' => 'Newaccount', 'created_at' => now()->subYears(User::RETENTION_YEARS)->addDay()]);
        $this->makeUser('officer', ['last_name' => 'Offalready', 'status' => 'deactivated', 'created_at' => now()->subYears(9)]);
        $admin = $this->makeUser('admin', ['created_at' => now()->subYears(9)]);

        $this->actingAs($admin)->get('/admin/users')
            ->assertSee('1 active account is older than '.User::RETENTION_YEARS.' years.');

        $this->actingAs($admin)->get('/admin/users?review=1')
            ->assertViewHas('users', fn ($users) => $users->count() === 1 && $users->first()->is($old))
            ->assertSee('Show everyone');
    }

    public function test_the_review_note_stays_hidden_when_nothing_is_old(): void
    {
        $this->actingAs($this->makeUser('admin'))->get('/admin/users')->assertDontSee('older than');
    }

    // ---------- Helpers ----------

    private function form(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'MJ', 'last_name' => 'Labastida', 'student_id' => '2201234',
            'organization_id' => $this->digits->id, 'email' => 'mj@lnu.edu.ph', 'phone' => '09171234567',
            'position' => 'Secretary', 'password' => 'Secret#2026', 'privacy_accepted' => '1',
            'id_document' => UploadedFile::fake()->image('school-id.jpg', 600, 380),
        ], $overrides);
    }

    private function makeUser(string $role, array $extra = []): User
    {
        static $id = 0;
        $id++;

        $user = User::factory()->create($extra + [
            'role' => $role, 'status' => 'verified', 'login_id' => "TEST-{$id}",
            'organization_id' => $role === 'officer' ? $this->digits->id : null,
        ]);

        // created_at is set by the database layer on create, so force an old date afterwards
        if (isset($extra['created_at'])) {
            $user->forceFill(['created_at' => $extra['created_at']])->saveQuietly();
        }

        return $user;
    }
}
