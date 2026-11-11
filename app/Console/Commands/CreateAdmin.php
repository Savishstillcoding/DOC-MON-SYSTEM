<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Adds an admin account (AUD-091). Admins can't sign up, and the seeder refuses to run
 * on the live site, so this is the only way to add a second admin (one admin = one
 * forgotten password away from nobody being able to verify accounts).
 *
 *   Local:  php artisan docmon:create-admin
 *   Live:   php artisan docmon:create-admin --env=backup   (MJ only, see DEPLOY.md)
 *
 * The password is typed hidden and never printed, logged, or put in shell history.
 * The new admin accepts the privacy notice at their first log-in, like everyone else.
 */
class CreateAdmin extends Command
{
    protected $signature = 'docmon:create-admin';

    protected $description = 'Add an admin account (hidden password prompt)';

    public function handle(): int
    {
        $firstName = text('First name', required: true, validate: fn ($v) => $this->check('first_name', $v, ['max:255']));
        $lastName = text('Last name', required: true, validate: fn ($v) => $this->check('last_name', $v, ['max:255']));
        $username = text('Username (for /login/admin)', required: true,
            validate: fn ($v) => $this->check('login_id', $v, ['max:30', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:users,login_id']));
        $email = text('Email (for password reset)', required: true,
            validate: fn ($v) => $this->check('email', $v, ['email', 'max:255', 'unique:users,email']));

        // Say exactly WHICH database will change, so the live one is never touched by accident
        $this->table(['New admin', 'Username', 'Email', 'Environment', 'Database'], [[
            "{$lastName}, {$firstName}", $username, $email, app()->environment(), DB::connection()->getDatabaseName(),
        ]]);

        if (! confirm('Create this admin account?', default: false)) {
            $this->info('Nothing changed.');

            return self::SUCCESS;
        }

        $new = password('Password (8+ characters, letters and numbers)', required: true,
            validate: fn ($v) => $this->check('password', $v, [Password::defaults()]));

        if (password('Type it again') !== $new) {
            $this->error('The two passwords are different. Nothing changed.');

            return self::FAILURE;
        }

        // role and status aren't fillable on purpose (a sign-up form can't set them), so forceFill
        $admin = new User(['first_name' => $firstName, 'last_name' => $lastName, 'login_id' => $username, 'email' => $email]);
        $admin->forceFill(['role' => 'admin', 'status' => 'verified', 'password' => $new])->save();   // hashed by the cast

        ActivityLog::record(null, 'accounts', 'Admin account created',
            "{$admin->displayName()} ({$admin->login_id}) · by a developer with php artisan docmon:create-admin");

        $this->info("Done. {$admin->displayName()} can log in at /login/admin as {$admin->login_id}.");

        return self::SUCCESS;
    }

    /** One field's first validation error, or null when it's fine (what the prompts expect). */
    private function check(string $field, mixed $value, array $rules): ?string
    {
        return Validator::make([$field => $value], [$field => $rules])->errors()->first($field) ?: null;
    }
}
