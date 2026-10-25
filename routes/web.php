<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landingpage');
});

// ---------- Guests only (logged-in users are sent to their dashboard) ----------
Route::middleware('guest')->group(function () {
    Route::get('/signup', function () {
        return view('signup');
    });

    Route::get('/register/officer', function () { return view('auth.studentofficer-signup'); });
    Route::get('/register/signatory', function () { return view('auth.signatory-signup'); });

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

// Registration forms. Officers and signatories share one form (same fields in
// the Figma design), so one view is used with a different title and tagline.
// Admin is intentionally not here: admin accounts shouldn't be self-registered.

/*
$registerRoles = [
    'officer' => ['label' => 'Student Officer', 'tagline' => 'New Secretary? Welcome aboard :D'],
    'signatory' => ['label' => 'Signatory', 'tagline' => 'New Signatory? Welcome aboard :D'],
];

Route::get('/register/{role}', function (string $role) use ($registerRoles) {
    return view('register', [
        'roleLabel' => $registerRoles[$role]['label'],
        'tagline' => $registerRoles[$role]['tagline'],
    ]);
})->whereIn('role', array_keys($registerRoles));
*/

// PROTOTYPE: accepts the form but saves nothing, then continues to Log-In.
// Replace with a real controller (validation, hashing, file storage) later.

Route::post('/register/{role}', function () {
    return redirect('/login');
})->whereIn('role', ['officer', 'signatory'])->middleware('guest');

//for future use

/*})->whereIn('role', array_keys($registerRoles));*/

// ---------- Dashboards: logged in AND the right role ----------
// 'auth' sends guests to /login; 'role:x' answers 403 to other roles.
// PROTOTYPE: the pages still show placeholder data (not the logged-in user's).
Route::middleware(['auth', 'role:officer'])->prefix('officer')->group(function () {
    Route::get('/dashboard', function () { return view('student-officer.dashboard'); });
    Route::get('/tickets', function () { return view('student-officer.my-tickets'); });
    Route::get('/tickets/new', function () { return view('student-officer.new-ticket'); });
    Route::get('/signatories', function () { return view('student-officer.signatories'); });
    Route::get('/profile', function () { return view('student-officer.profile'); });
});

Route::middleware(['auth', 'role:signatory'])->prefix('signatory')->group(function () {
    Route::get('/dashboard', function () { return view('signatory.dashboard'); });
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', function () { return view('admin.dashboard'); });
});

// Dev-only reference page showing every dashboard component.
Route::get('/dev/components', function () { return view('docmon-dev.components-preview'); });
