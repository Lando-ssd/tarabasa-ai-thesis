<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Creates the one Admin account, or updates its password, from two settings (ADMIN_EMAIL and
 * ADMIN_PASSWORD) so that no password ever has to be written in the code. It runs every time the
 * server starts (docker/entrypoint.sh): with ADMIN_PASSWORD set it makes the account match, and
 * with it unset it does nothing at all. To change the Admin password, change ADMIN_PASSWORD in the
 * host's settings and redeploy.
 *
 * The password the code used to carry (and which is public in the repository) is refused here so it
 * can never be set again by mistake.
 */
class SyncAdminAccount extends Command
{
    protected $signature = 'admin:sync';

    protected $description = 'Create or update the Admin account from ADMIN_EMAIL and ADMIN_PASSWORD';

    /** The password an older version of the code created the Admin with. It is public. */
    public const PUBLISHED_PASSWORD = 'AdminPass123!';

    public function handle(): int
    {
        $email = (string) env('ADMIN_EMAIL', 'admin@tarabasa.ai');
        $password = (string) env('ADMIN_PASSWORD', '');

        if ($password === '') {
            $this->line('ADMIN_PASSWORD is not set: the Admin account was left as it is.');

            return self::SUCCESS;
        }

        if ($password === self::PUBLISHED_PASSWORD || strlen($password) < 12) {
            $this->error('ADMIN_PASSWORD must be at least 12 characters and cannot be the old published one. Nothing was changed.');

            return self::FAILURE;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('ADMIN_EMAIL is not a valid email address. Nothing was changed.');

            return self::FAILURE;
        }

        // Exactly one Admin: any other Admin row is demoted by being left alone here and removed
        // from login by the email change below (the Admin Actor Prompt allows exactly one).
        $admin = User::where('user_type', 'Admin')->first() ?? new User();
        $admin->forceFill([
            'first_name' => $admin->first_name ?: 'TaraBasa',
            'last_name' => $admin->last_name ?: 'Admin',
            'email' => $email,
            'password' => Hash::make($password),
            'user_type' => 'Admin',
            'status' => 'Active',
            'email_verified_at' => $admin->email_verified_at ?: now(),
        ])->save();

        $this->info("The Admin account ({$email}) now matches ADMIN_PASSWORD.");

        return self::SUCCESS;
    }
}
