<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoApiTransport;
use Tests\TestCase;

/**
 * "Forgot password?" (AUD-008): reset link by email, through Brevo on the live site.
 * Email is faked here: nothing is really sent.
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    /** The one answer the form gives, word for word, whether or not the account exists */
    private const SENT = 'If the ID and email match a verified account, we emailed a reset link. Check your inbox and spam folder; the link works for 60 minutes.';

    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->officer = User::factory()->create([
            'role' => 'officer', 'status' => 'verified', 'login_id' => '2201234', 'email' => 'mj@example.com',
        ]);
    }

    public static function loginPages(): array
    {
        return [['/login/officer'], ['/login/signatory'], ['/login/admin']];
    }

    #[DataProvider('loginPages')]
    public function test_every_log_in_page_links_to_forgot_password(string $page): void
    {
        // Was href="#"
        $this->get($page)->assertSee('href="'.url('/forgot-password').'">Forgot password?', false);
        $this->get('/forgot-password')->assertOk()->assertSee('Email me a reset link');
    }

    // ---------- Asking for the link ----------

    public function test_the_right_id_and_email_get_a_one_time_link(): void
    {
        Notification::fake();

        $this->from('/forgot-password')->post('/forgot-password', ['login_id' => '2201234', 'email' => 'mj@example.com'])
            ->assertRedirect('/forgot-password')
            ->assertSessionHas('status', self::SENT);

        Notification::assertSentTo($this->officer, ResetPasswordNotification::class, function ($notification) {
            $mail = $notification->toMail($this->officer);

            return $mail->subject === 'Reset your DOC-MON password'
                && $mail->actionUrl === url("/reset-password/{$notification->token}?email=".urlencode('mj@example.com'));
        });
        $this->assertSame(1, DB::table('password_reset_tokens')->count());
        $this->assertSame('Requested password reset', ActivityLog::latest('id')->first()->action);
    }

    public static function noMatch(): array
    {
        return [
            'wrong email' => ['2201234', 'someone@example.com'],
            'wrong ID' => ['2209999', 'mj@example.com'],
            'unknown both' => ['0000000', 'nobody@example.com'],
        ];
    }

    #[DataProvider('noMatch')]
    public function test_a_wrong_id_or_email_gets_no_link_but_the_same_answer(string $id, string $email): void
    {
        Notification::fake();

        // Same message as a match, so the form can't reveal which IDs or emails exist
        $this->post('/forgot-password', ['login_id' => $id, 'email' => $email])
            ->assertSessionHas('status', self::SENT);

        Notification::assertNothingSent();
    }

    public function test_accounts_that_cannot_log_in_get_no_link(): void
    {
        Notification::fake();

        foreach (['pending', 'rejected', 'deactivated'] as $status) {
            $this->officer->forceFill(['status' => $status])->save();
            $this->post('/forgot-password', ['login_id' => '2201234', 'email' => 'mj@example.com'])
                ->assertSessionHas('status', self::SENT);
        }

        Notification::assertNothingSent();
    }

    public function test_the_form_is_rate_limited(): void
    {
        Notification::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/forgot-password', ['login_id' => '2201234', 'email' => 'mj@example.com']);
        }

        $this->post('/forgot-password', ['login_id' => '2201234', 'email' => 'mj@example.com'])->assertStatus(429);
        // And Laravel sends at most one email per account per minute
        Notification::assertSentToTimes($this->officer, ResetPasswordNotification::class, 1);
    }

    // ---------- Using the link ----------

    public function test_the_link_sets_a_new_password_once_and_signs_out_everywhere(): void
    {
        $token = $this->requestToken();
        DB::table('sessions')->insert(['id' => 'stolen', 'user_id' => $this->officer->id, 'payload' => '', 'last_activity' => time()]);

        $this->get("/reset-password/{$token}?email=mj%40example.com")
            ->assertOk()
            ->assertSee('value="'.$token.'"', false)
            ->assertSee('value="mj@example.com"', false)
            ->assertSee('<meta name="referrer" content="no-referrer">', false);

        $this->post('/reset-password', $this->resetForm($token))
            ->assertRedirect('/login/officer')
            ->assertSessionHas('status', 'Password changed. Log in with your new password.');

        $this->assertTrue(Hash::check('NewPass#2026', $this->officer->fresh()->password));
        $this->assertSame(0, DB::table('sessions')->where('user_id', $this->officer->id)->count());
        $this->assertSame('Reset password', ActivityLog::latest('id')->first()->action);
        $this->post('/login/officer', ['student_id' => '2201234', 'password' => 'NewPass#2026'])->assertRedirect('/officer/dashboard');

        // The same link can't be used again
        auth()->logout();
        $this->post('/reset-password', $this->resetForm($token, 'Another#2026'))->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('NewPass#2026', $this->officer->fresh()->password));
    }

    public function test_an_old_or_fake_link_does_not_work(): void
    {
        $token = $this->requestToken();
        $this->travel(61)->minutes();

        $this->post('/reset-password', $this->resetForm($token))
            ->assertSessionHasErrors(['email' => 'This reset link is invalid or has expired. Ask for a new one.']);
        $this->post('/reset-password', $this->resetForm('made-up-token'))->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password', $this->officer->fresh()->password));
    }

    public function test_a_link_only_works_for_its_own_email(): void
    {
        $token = $this->requestToken();
        User::factory()->create(['role' => 'officer', 'status' => 'verified', 'login_id' => '2205555', 'email' => 'other@example.com']);

        $this->post('/reset-password', ['email' => 'other@example.com'] + $this->resetForm($token))->assertSessionHasErrors('email');
    }

    public function test_the_new_password_follows_the_profile_rules(): void
    {
        $token = $this->requestToken();

        $this->post('/reset-password', $this->resetForm($token, 'short'))->assertSessionHasErrors('password');
        $this->post('/reset-password', ['password_confirmation' => 'Different#1'] + $this->resetForm($token))->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('password', $this->officer->fresh()->password));
    }

    // ---------- Brevo (the live mailer) ----------

    public function test_the_email_goes_to_brevos_api_from_doc_mon(): void
    {
        $sent = [];
        $this->useFakeBrevo(function (string $method, string $url, array $options) use (&$sent) {
            $sent = ['url' => $url, 'key' => $options['normalized_headers']['api-key'][0] ?? null, 'body' => json_decode($options['body'], true)];

            return new JsonMockResponse(['messageId' => '<1@brevo>'], ['http_code' => 201]);
        });

        $this->post('/forgot-password', ['login_id' => '2201234', 'email' => 'mj@example.com']);

        $this->assertSame('https://api.brevo.com/v3/smtp/email', $sent['url']);
        $this->assertSame('api-key: test-key', $sent['key']);
        $this->assertSame('mj@example.com', $sent['body']['to'][0]['email']);
        $this->assertEquals(['name' => 'DOC-MON', 'email' => 'sender@example.com'], $sent['body']['sender']);   // any order
        $this->assertSame('Reset your DOC-MON password', $sent['body']['subject']);
        $this->assertStringContainsString(url('/reset-password/'), $sent['body']['htmlContent']);
    }

    public function test_when_brevo_fails_the_page_still_answers_and_the_team_sees_it_in_the_log(): void
    {
        // e.g. the API key expired after 90 days without use
        $this->useFakeBrevo(fn () => new JsonMockResponse(['code' => 'unauthorized', 'message' => 'Key not found'], ['http_code' => 401]));
        Log::spy();

        $this->post('/forgot-password', ['login_id' => '2201234', 'email' => 'mj@example.com'])
            ->assertSessionHas('status', self::SENT);

        Log::shouldHaveReceived('error')->withArgs(fn ($message) => str_starts_with($message, 'Password reset email failed'));
    }

    // ---------- Helpers ----------

    /** Asks for a link and returns the token from the (fake) email. */
    private function requestToken(): string
    {
        Notification::fake();
        $this->post('/forgot-password', ['login_id' => '2201234', 'email' => 'mj@example.com']);

        $token = null;
        Notification::assertSentTo($this->officer, ResetPasswordNotification::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });

        return $token;
    }

    private function resetForm(string $token, string $password = 'NewPass#2026'): array
    {
        return ['token' => $token, 'email' => 'mj@example.com', 'password' => $password, 'password_confirmation' => $password];
    }

    /** The real Brevo transport, but talking to a fake Brevo server. */
    private function useFakeBrevo(callable $server): void
    {
        config(['mail.default' => 'brevo', 'mail.from.address' => 'sender@example.com', 'mail.from.name' => 'DOC-MON']);
        Mail::extend('brevo', fn () => new BrevoApiTransport('test-key', new MockHttpClient($server, 'https://api.brevo.com')));
    }
}
