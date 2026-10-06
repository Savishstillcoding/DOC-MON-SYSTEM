<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landingpage');
});

Route::get('/signup', function () {
    return view('signup');
});

Route::get('/register/officer', function () { return view('auth.studentofficer-signup'); });
Route::get('/register/signatory', function () { return view('auth.signatory-signup'); });

Route::get('/login', function() { return view('login'); });
Route::get('/login/officer', function () { return view('auth.studentofficer-login'); });
Route::get('/login/signatory', function () { return view('auth.signatory-login'); });
Route::get('/login/admin', function () { return view('auth.admin-login'); });

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
})->whereIn('role', ['officer', 'signatory']);

//for future use

/*})->whereIn('role', array_keys($registerRoles));*/
