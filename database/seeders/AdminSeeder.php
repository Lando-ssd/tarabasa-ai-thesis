<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * There is exactly one seeded Admin account, ever — no self-registration
     * exists for this role (Admin Actor Prompt, Section 1). updateOrCreate
     * keyed on email makes re-running this seeder safe: it won't create a
     * second Admin if it's run more than once.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@tarabasa.ai'],
            [
                'first_name' => 'TaraBasa',
                'last_name' => 'Admin',
                'password' => Hash::make('AdminPass123!'),
                'user_type' => 'Admin',
                'status' => 'Active',
                'email_verified_at' => now(),
            ]
        );
    }
}
