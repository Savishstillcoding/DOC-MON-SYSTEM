<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Demo data for local development and the panel demo:
     * DIGITS, its 4 signatories in order, one officer, one admin,
     * and one officer still waiting for verification.
     *
     * Every demo account's password is "password" (from UserFactory).
     * Run on an empty database: php artisan migrate:fresh --seed
     */
    public function run(): void
    {
        // Demo accounts with a known password must never reach the live site
        if (app()->environment('production')) {
            $this->command?->warn('Skipped: demo accounts are not created in production.');

            return;
        }

        $digits = DB::table('organizations')->insertGetId([
            'code' => 'DIGITS',
            'name' => 'Digital Integrated Group of IT Students',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Admin: logs in at /login/admin with username "admin"
        User::factory()->create([
            'role' => 'admin', 'status' => 'verified', 'login_id' => 'admin',
            'first_name' => 'System', 'last_name' => 'Admin', 'email' => 'admin@example.com',
        ]);

        // Officer: logs in at /login/officer with Student ID 2201234
        User::factory()->create([
            'role' => 'officer', 'status' => 'verified', 'login_id' => '2201234',
            'first_name' => 'MJ Bryan', 'last_name' => 'Labastida', 'email' => 'mj.labastida@example.com',
            'phone' => '09171234567', 'position' => 'Secretary', 'organization_id' => $digits,
        ]);

        // Pending officer: correct password, but blocked until the admin verifies them
        User::factory()->create([
            'role' => 'officer', 'status' => 'pending', 'login_id' => '2209876',
            'first_name' => 'Jessa', 'last_name' => 'Santos', 'email' => 'jessa.santos@example.com',
            'position' => 'Deputy Secretary', 'organization_id' => $digits,
        ]);

        // Signatories: log in at /login/signatory with their Employee ID
        $route = [
            [1, 'Adviser',       'EMP-0451', 'Ana',   'Dela Cruz', 'In a Meeting',  '2:00 PM'],
            [2, 'SSC President', 'EMP-0452', 'Paolo', 'Reyes',     'Available',     null],
            [3, 'ODSOA',         'EMP-0122', 'Ramon', 'Garcia',    'Out of Office', 'Tomorrow'],
            [4, 'VPSD',          'EMP-0300', 'Liza',  'Santos',    'Available',     null],
        ];

        foreach ($route as [$step, $title, $employeeId, $first, $last, $availability, $backAt]) {
            $signatory = User::factory()->create([
                'role' => 'signatory', 'status' => 'verified', 'login_id' => $employeeId,
                'first_name' => $first, 'last_name' => $last, 'position' => $title,
                'email' => strtolower($first).'.'.strtolower(str_replace(' ', '', $last)).'@example.com',
                'availability' => $availability, 'back_at' => $backAt,
            ]);

            DB::table('organization_signatory')->insert([
                'organization_id' => $digits,
                'user_id' => $signatory->id,
                'step' => $step,
                'title' => $title,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
