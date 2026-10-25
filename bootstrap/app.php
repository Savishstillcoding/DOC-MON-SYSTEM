<?php

use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        // 'role:officer' etc. in routes/web.php
        $middleware->alias(['role' => EnsureUserHasRole::class]);

        // Not logged in and opens a protected page → go to log-in
        $middleware->redirectGuestsTo('/login');

        // Already logged in and opens a log-in/sign-up page → go to own dashboard
        $middleware->redirectUsersTo(fn (Request $request) => '/'.$request->user()->role.'/dashboard');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
