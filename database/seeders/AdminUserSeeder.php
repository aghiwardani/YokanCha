<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds the single owner/admin account used to log in at /admin/login.
 *
 * PLACEHOLDER CREDENTIALS — the owner MUST change these after first login
 * (or before deploying to production):
 *   email:    admin@yokancha.local
 *   password: ChangeMe123!
 *
 * Run with: php artisan db:seed --class=AdminUserSeeder
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@yokancha.local'],
            [
                'name' => 'YokanCha Admin',
                'password' => Hash::make('ChangeMe123!'),
            ]
        );
    }
}
