<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * There is exactly one seeded Admin account, ever (Admin Actor Prompt, Section 1; no
     * self-registration exists for this role). The password is NOT in the code: it comes from
     * ADMIN_PASSWORD (a repository can be public, and this one is). Without it, this seeds nothing,
     * so an Admin with a guessable password can never be created by mistake. On the live site the
     * same setting is applied at every start by `php artisan admin:sync`.
     */
    public function run(): void
    {
        $password = (string) env('ADMIN_PASSWORD', '');

        if ($password === '') {
            $this->command?->warn('ADMIN_PASSWORD is not set, so no Admin account was created.');

            return;
        }

        User::updateOrCreate(
            ['email' => (string) env('ADMIN_EMAIL', 'admin@tarabasa.ai')],
            [
                'first_name' => 'TaraBasa',
                'last_name' => 'Admin',
                'password' => Hash::make($password),
                'user_type' => 'Admin',
                'status' => 'Active',
                'email_verified_at' => now(),
            ]
        );
    }
}
