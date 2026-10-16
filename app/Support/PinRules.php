<?php

namespace App\Support;

use Illuminate\Support\Facades\Hash;

/**
 * What a child's 4 digit PIN may be when a guardian sets a new one, and how often.
 *
 * A PIN is short on purpose (a Grade 1 child must be able to type it), so the rules keep out the ones a classmate would try first:
 * four of the same number (1111) and a straight run (1234, 4321). A new PIN also has to be different from the one it replaces, or a
 * "reset" would change nothing. The same rules apply to a PIN the system makes, so a made-up PIN is never a weak one.
 */
final class PinRules
{
    /** At most this many changes for one child in 24 hours. It stops guessing games and a guardian going round in circles. */
    public const CHANGES_PER_DAY = 3;

    /** A plain sentence saying why this PIN is not allowed, or null when it is fine. */
    public static function refusal(string $pin, ?string $currentHash = null): ?string
    {
        if (! preg_match('/^\d{4}$/', $pin)) {
            return 'The PIN must be exactly 4 numbers.';
        }

        if (count(array_unique(str_split($pin))) === 1) {
            return 'Choose a PIN that is not four of the same number, like 1111.';
        }

        if (self::isRun($pin)) {
            return 'Choose a PIN that is not a run of numbers, like 1234 or 4321.';
        }

        if ($currentHash !== null && $currentHash !== '' && Hash::check($pin, $currentHash)) {
            return 'That is already the PIN. Choose a different one.';
        }

        return null;
    }

    /** A random 4 digit PIN that passes every rule (made with the system's secure random numbers, not mt_rand). */
    public static function generate(?string $currentHash = null): string
    {
        do {
            $pin = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (self::refusal($pin, $currentHash) !== null);

        return $pin;
    }

    private static function isRun(string $pin): bool
    {
        $d = array_map('intval', str_split($pin));
        $up = true;
        $down = true;

        for ($i = 1; $i < 4; $i++) {
            $up = $up && $d[$i] === $d[$i - 1] + 1;
            $down = $down && $d[$i] === $d[$i - 1] - 1;
        }

        return $up || $down;
    }
}
