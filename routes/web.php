<?php

/*
| READING GUIDE 1/15: routes/web.php
|
| WHAT: the route list: every web address (URL) in DOC-MON.
| USED FOR: telling Laravel which code runs when someone opens a page or sends
|           a form.
| KEY IDEA: 'auth' = must be logged in, 'role:officer' = must be that role,
|           'throttle' = limits how many tries (stops password guessing).
*/

use App\Http\Controllers\Account\PasswordController;
use App\Http\Controllers\Account\TelegramController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\OrganizationController;
use App\Http\Controllers\Admin\SignatoryListController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\PrivacyController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Officer\DashboardController as OfficerDashboardController;
use App\Http\Controllers\Officer\DocumentController;
use App\Http\Controllers\Officer\LiveController as OfficerLiveController;
use App\Http\Controllers\Officer\ProfileController;
use App\Http\Controllers\Officer\SignatoryController;
use App\Http\Controllers\Officer\TicketController;
use App\Http\Controllers\Signatory\AvailabilityController;
use App\Http\Controllers\Signatory\DashboardController;
use App\Http\Controllers\Signatory\DocumentActionController;
use App\Http\Controllers\Signatory\HistoryController;
use App\Http\Controllers\Signatory\ProfileController as SignatoryProfileController;
use App\Http\Controllers\Signatory\ReviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landingpage');
});

// ---------- Pages for people who are NOT logged in yet ----------
// ('guest': someone who is already logged in is sent to their own dashboard instead)
Route::middleware('guest')->group(function () {
    Route::get('/signup', function () {
        return view('signup');
    });

    // Sign-up: saves a new account as "pending" (waiting for the admin) together with a
    // photo of the person's ID. throttle:5,10 = at most 5 sign-ups per 10 minutes from one
    // internet address, because anyone can upload a 2 MB file here without logging in, and
    // a bot could otherwise fill up our database.
    Route::get('/register/{role}', [RegisterController::class, 'create'])->whereIn('role', ['officer', 'signatory']);
    Route::post('/register/{role}', [RegisterController::class, 'store'])
        ->whereIn('role', ['officer', 'signatory'])
        ->middleware('throttle:5,10');

    Route::get('/login', function () {
        return view('login');
    });
    Route::get('/login/officer', function () {
        return view('auth.studentofficer-login');
    });
    Route::get('/login/signatory', function () {
        return view('auth.signatory-login');
    });
    Route::get('/login/admin', function () {
        return view('auth.admin-login');
    });

    // The pages above only SHOW the log-in forms; this one actually logs you in. It checks
    // the ID, password and role, and only lets in accounts the admin has verified.
    // throttle:login = at most 5 tries per minute from one internet address and 10 per
    // account, so nobody can guess a password by trying thousands of times.
    // (The limits are set in app/Providers/AppServiceProvider.php)
    Route::post('/login/{role}', [LoginController::class, 'store'])
        ->whereIn('role', ['officer', 'signatory', 'admin'])
        ->middleware('throttle:login');

    // Forgot password: emails a link that works only once. throttle:5,10 = at most 5
    // requests per 10 minutes, so nobody can flood someone's inbox with reset emails
    // (Laravel also sends at most one email per account per minute).
    Route::get('/forgot-password', [PasswordResetController::class, 'create']);
    Route::post('/forgot-password', [PasswordResetController::class, 'store'])->middleware('throttle:5,10');
    // ->name('password.reset') gives this URL a name; the reset email uses that name to build its link
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:5,1');
});

// Log-out is a form (POST), not a link, so another website can't log you out with a hidden link
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth');

// Privacy notice: anyone can read it at /privacy; a logged-in user presses Accept once.
Route::get('/privacy', [PrivacyController::class, 'show']);
Route::post('/privacy/accept', [PrivacyController::class, 'accept'])->middleware('auth');

// ---------- Dashboards: you must be logged in AND have the right role ----------
// 'auth'    not logged in? Go to /login.
// 'role:x'  logged in as a different role? "403 Forbidden".
// 'privacy' haven't accepted the privacy notice yet? Go to /privacy first.
// prefix('officer'): every URL in the group starts with /officer, e.g. '/tickets' = /officer/tickets.
Route::middleware(['auth', 'role:officer', 'privacy'])->prefix('officer')->group(function () {
    Route::get('/dashboard', [OfficerDashboardController::class, 'index']);
    Route::get('/tickets', [TicketController::class, 'index']);
    Route::get('/tickets/new', [TicketController::class, 'create']);
    Route::post('/tickets', [TicketController::class, 'store']);
    Route::post('/documents/{document}/resubmit', [DocumentController::class, 'resubmit']);
    Route::get('/signatories', [SignatoryController::class, 'index']);
    Route::get('/live', OfficerLiveController::class);
    Route::get('/profile', [ProfileController::class, 'edit']);
    Route::put('/profile', [ProfileController::class, 'update']);
    // throttle:5,1 = at most 5 tries per minute: this form asks for the current password,
    // so like the log-in page it must not allow guessing
    Route::put('/profile/password', [PasswordController::class, 'update'])->middleware('throttle:5,1');
    Route::post('/telegram/connect', [TelegramController::class, 'connect']);
    // throttle:10,1 = at most 10 clicks per minute: each click sends a request to Telegram's servers
    Route::post('/telegram/check', [TelegramController::class, 'check'])->middleware('throttle:10,1');
    Route::delete('/telegram', [TelegramController::class, 'disconnect']);
});

Route::middleware(['auth', 'role:signatory', 'privacy'])->prefix('signatory')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/review', [ReviewController::class, 'index']);
    Route::get('/history', [HistoryController::class, 'index']);
    Route::post('/documents/{document}/action', [DocumentActionController::class, 'store']);
    Route::post('/tickets/{ticket}/approve-all', [DocumentActionController::class, 'approveAll']);
    Route::get('/profile', [SignatoryProfileController::class, 'edit']);
    Route::put('/profile', [SignatoryProfileController::class, 'update']);
    Route::put('/profile/password', [PasswordController::class, 'update'])->middleware('throttle:5,1');
    Route::post('/telegram/connect', [TelegramController::class, 'connect']);
    Route::post('/telegram/check', [TelegramController::class, 'check'])->middleware('throttle:10,1');
    Route::delete('/telegram', [TelegramController::class, 'disconnect']);
    Route::put('/availability', [AvailabilityController::class, 'update']);
});

Route::middleware(['auth', 'role:admin', 'privacy'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index']);
    Route::get('/users/{user}/id-document', [VerificationController::class, 'showId']);
    Route::get('/verification', [VerificationController::class, 'index']);
    Route::post('/users/{user}/review', [VerificationController::class, 'review']);
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{user}/edit', [UserController::class, 'edit']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::post('/users/{user}/deactivate', [UserController::class, 'deactivate']);
    Route::post('/users/{user}/reactivate', [UserController::class, 'reactivate']);
    Route::get('/activity-logs', [ActivityLogController::class, 'index']);
    Route::get('/organizations', [OrganizationController::class, 'index']);
    Route::get('/organizations/new', [OrganizationController::class, 'create']);
    Route::post('/organizations', [OrganizationController::class, 'store']);
    Route::get('/organizations/{organization}/edit', [OrganizationController::class, 'edit']);
    Route::put('/organizations/{organization}', [OrganizationController::class, 'update']);
    Route::delete('/organizations/{organization}', [OrganizationController::class, 'destroy']);
    Route::get('/signatory-lists', [SignatoryListController::class, 'index']);
    Route::put('/signatory-lists/{organization}', [SignatoryListController::class, 'update']);
});

// A page only for us developers that shows every dashboard part (cards, badges, buttons).
// It only exists on a developer's laptop (APP_ENV=local); on the live site this URL gives 404.
if (app()->environment('local')) {
    Route::get('/dev/components', function () {
        return view('docmon-dev.components-preview');
    });
}
