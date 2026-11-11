<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\NotPwnedVerifier;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoApiTransport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The leaked-password check (AUD-067) waits at most 5 seconds instead of Laravel's 30.
        // If haveibeenpwned.com can't be reached, Laravel lets the password through, so a
        // slow or offline service never blocks sign-up. Only the first 5 characters of the
        // password's SHA-1 hash are sent, never the password itself.
        $this->app->extend(UncompromisedVerifier::class, fn ($verifier, $app) => new NotPwnedVerifier($app->make(HttpFactory::class), 5));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        // One password rule for sign-up, Profile, the reset link and docmon:reset-password
        // (AUD-067): 8+ characters with letters and numbers, and not in a known data leak.
        // Existing passwords keep working; the rule applies when a password is set
        Password::defaults(fn () => Password::min(8)->letters()->numbers()->uncompromised());

        // Log-in limits (AUD-073): 5 tries a minute per IP address, as before, plus 10 a
        // minute per account, so switching IP addresses doesn't allow endless guessing.
        // Kept to one minute, so a stranger can't keep someone locked out for long
        RateLimiter::for('login', function (Request $request) {
            $loginId = Str::lower(trim((string) ($request->input('student_id') ?? $request->input('employee_id') ?? $request->input('username'))));

            return [
                Limit::perMinute(5)->by('ip|'.$request->ip()),
                Limit::perMinute(10)->by('account|'.$request->route('role').'|'.$loginId),
            ];
        });

        // MAIL_MAILER=brevo: send through Brevo's API over HTTPS (Symfony's Brevo bridge).
        // 10-second limit, so a slow Brevo can't hang the "Forgot password?" page.
        Mail::extend('brevo', fn () => new BrevoApiTransport(
            (string) config('services.brevo.key'),
            HttpClient::create(['timeout' => 10]),
        ));
    }
}
