<?php

namespace App\Providers;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoApiTransport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        // MAIL_MAILER=brevo: send through Brevo's API over HTTPS (Symfony's Brevo bridge).
        // 10-second limit, so a slow Brevo can't hang the "Forgot password?" page.
        Mail::extend('brevo', fn () => new BrevoApiTransport(
            (string) config('services.brevo.key'),
            HttpClient::create(['timeout' => 10]),
        ));
    }
}
