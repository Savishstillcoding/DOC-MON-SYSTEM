<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// role and status are left out on purpose: only the app (or an admin) may set
// them, so a sign-up form can't sneak in "role=admin" or "status=verified".
#[Fillable(['first_name', 'last_name', 'login_id', 'email', 'phone', 'position', 'organization_id', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Same defaults as the database, so a brand-new user has them right away
     * (otherwise $user->role is empty until the row is reloaded).
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'role' => 'officer',
        'status' => 'pending',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
