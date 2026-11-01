<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;

/**
 * Sets a new password for one account, for when someone forgot theirs.
 * Nobody can READ a password (only its bcrypt hash is stored), so the only fix is a new one.
 *
 *   Local:  php artisan docmon:reset-password admin
 *   Live:   php artisan docmon:reset-password admin --env=backup   (MJ only, see DEPLOY.md)
 *
 * The password is typed hidden and never printed, logged, or put in shell history.
 */
class ResetPassword extends Command
{
    protected $signature = 'docmon:reset-password {login_id : The log-in ID (admin username, student ID or employee ID)}';

    protected $description = 'Set a new password for one DOC-MON account (hidden prompt)';

    public function handle(): int
    {
        $user = User::where('login_id', $this->argument('login_id'))->first();

        if ($user === null) {
            $this->error('No account with that log-in ID.');

            return self::FAILURE;
        }

        // Say exactly WHICH database will change, so the live one is never touched by accident
        $this->table(['Account', 'Role', 'Status', 'Environment', 'Database'], [[
            "{$user->displayName()} ({$user->login_id})", $user->role, $user->status,
            app()->environment(), DB::connection()->getDatabaseName(),
        ]]);

        if (! confirm('Set a new password for this account?', default: false)) {
            $this->info('Nothing changed.');

            return self::SUCCESS;
        }

        $new = password('New password (at least 8 characters)', required: true, validate: fn ($value) => Validator::make(
            ['password' => $value], ['password' => [Password::defaults()]]
        )->errors()->first('password') ?: null);

        if (password('Type it again') !== $new) {
            $this->error('The two passwords are different. Nothing changed.');

            return self::FAILURE;
        }

        $user->update(['password' => $new]);   // hashed by the User model's cast

        // Sign the account out everywhere, in case someone else knew the old password
        if (config('session.driver') === 'database' && Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        $this->info("Done. {$user->displayName()} can log in with the new password.");

        return self::SUCCESS;
    }
}
