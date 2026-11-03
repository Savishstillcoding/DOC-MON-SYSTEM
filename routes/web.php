<?php

use App\Http\Controllers\Account\PasswordController;
use App\Http\Controllers\Account\TelegramController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\OrganizationController;
use App\Http\Controllers\Admin\SignatoryListController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Officer\DashboardController as OfficerDashboardController;
use App\Http\Controllers\Officer\DocumentController;
use App\Http\Controllers\Officer\LiveController as OfficerLiveController;
use App\Http\Controllers\Officer\SignatoryController;
use App\Http\Controllers\Officer\ProfileController;
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

// ---------- Guests only (logged-in users are sent to their dashboard) ----------
Route::middleware('guest')->group(function () {
    Route::get('/signup', function () {
        return view('signup');
    });

    // Sign-up (AUD-044): saves a pending account with its ID photo. Throttled because
    // anyone can post 2 MB files here without logging in: 5 sign-ups per 10 minutes per IP.
    Route::get('/register/{role}', [RegisterController::class, 'create'])->whereIn('role', ['officer', 'signatory']);
    Route::post('/register/{role}', [RegisterController::class, 'store'])
        ->whereIn('role', ['officer', 'signatory'])
        ->middleware('throttle:5,10');

    Route::get('/login', function() { return view('login'); });
    Route::get('/login/officer', function () { return view('auth.studentofficer-login'); });
    Route::get('/login/signatory', function () { return view('auth.signatory-login'); });
    Route::get('/login/admin', function () { return view('auth.admin-login'); });

    // Real log-in: checks the ID + password + role, and only lets verified accounts in.
    // throttle:5,1 = at most 5 attempts per minute, to slow down password guessing.
    Route::post('/login/{role}', [LoginController::class, 'store'])
        ->whereIn('role', ['officer', 'signatory', 'admin'])
        ->middleware('throttle:5,1');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth');

// ---------- Dashboards: logged in AND the right role ----------
// 'auth' sends guests to /login; 'role:x' answers 403 to other roles.
Route::middleware(['auth', 'role:officer'])->prefix('officer')->group(function () {
    Route::get('/dashboard', [OfficerDashboardController::class, 'index']);
    Route::get('/tickets', [TicketController::class, 'index']);
    Route::get('/tickets/new', [TicketController::class, 'create']);
    Route::post('/tickets', [TicketController::class, 'store']);
    Route::post('/documents/{document}/resubmit', [DocumentController::class, 'resubmit']);
    Route::get('/signatories', [SignatoryController::class, 'index']);
    Route::get('/live', OfficerLiveController::class);
    Route::get('/profile', [ProfileController::class, 'edit']);
    Route::put('/profile', [ProfileController::class, 'update']);
    // Throttled like log-in: this form also checks a password, so it must not allow guessing
    Route::put('/profile/password', [PasswordController::class, 'update'])->middleware('throttle:5,1');
    Route::post('/telegram/connect', [TelegramController::class, 'connect']);
    // Throttled: each click asks Telegram's servers
    Route::post('/telegram/check', [TelegramController::class, 'check'])->middleware('throttle:10,1');
    Route::delete('/telegram', [TelegramController::class, 'disconnect']);
});

Route::middleware(['auth', 'role:signatory'])->prefix('signatory')->group(function () {
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

Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
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

// Dev-only reference page showing every dashboard component.
// Registered only on a developer's laptop (APP_ENV=local), so the live site answers 404.
if (app()->environment('local')) {
    Route::get('/dev/components', function () { return view('docmon-dev.components-preview'); });
}
