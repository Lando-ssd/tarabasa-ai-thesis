<?php

namespace App\Support;

use App\Models\Learner;

/**
 * Learner codes. New codes look like TB26-48293: TB, the two digit school year, four random
 * digits and a check digit (Luhn) that catches a mistyped code on the device before the server is
 * asked. The code carries no personal information: it is typed on shared devices and seen by
 * classmates (Republic Act 10173, data minimisation). Codes made before this (TB-48293, "old
 * codes") keep working everywhere; they have no check digit.
 *
 * A Teacher joins a learner with the last five characters (four digits and the check digit for a
 * new code, the five digits of an old one).
 */
class LearnerCode
{
    /** Luhn check digit for a string of digits: the digit that makes the whole number valid. */
    public static function checkDigit(string $digits): string
    {
        $sum = 0;
        $double = true;
        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $d = (int) $digits[$i];
            if ($double) {
                $d *= 2;
                if ($d > 9) {
                    $d -= 9;
                }
            }
            $sum += $d;
            $double = ! $double;
        }

        return (string) ((10 - $sum % 10) % 10);
    }

    /** A new, unused code such as TB26-48293. */
    public static function generate(?int $year = null): string
    {
        $yy = str_pad((string) (($year ?? (int) now()->format('Y')) % 100), 2, '0', STR_PAD_LEFT);

        do {
            $four = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $code = 'TB'.$yy.'-'.$four.self::checkDigit($yy.$four);
        } while (Learner::where('learner_code', $code)->exists());

        return $code;
    }

    /**
     * What a person typed, in the form it is stored: upper case, no spaces, hyphen in the right
     * place (TB2648293, tb26 48293 and TB26-48293 are all TB26-48293; TB12345 is TB-12345).
     */
    public static function normalize(string $raw): string
    {
        $code = strtoupper(preg_replace('/\s+/', '', $raw));

        if (preg_match('/^TB(\d{2})-?(\d{5})$/', $code, $m)) {
            return 'TB'.$m[1].'-'.$m[2];
        }

        if (preg_match('/^TB-?([0-9A-Z]{5})$/', $code, $m)) {
            return 'TB-'.$m[1];
        }

        return $code;
    }

    /** True when the text has the shape of a learner code, new or old. */
    public static function looksLikeCode(string $raw): bool
    {
        return (bool) preg_match('/^TB(\d{2}-\d{5}|-[0-9A-Z]{5})$/', self::normalize($raw));
    }

    /** New codes carry a check digit; old ones cannot be checked, so they pass. */
    public static function checkDigitOk(string $code): bool
    {
        if (! preg_match('/^TB(\d{2})-(\d{4})(\d)$/', self::normalize($code), $m)) {
            return true;
        }

        return self::checkDigit($m[1].$m[2]) === $m[3];
    }

    /**
     * Learners whose code ends in these five characters (what a Teacher types), or the one learner
     * whose code is typed in full. More than one match means the Teacher needs the whole code.
     *
     * @return \Illuminate\Support\Collection<int, Learner>
     */
    public static function matching(string $typed)
    {
        $normalized = self::normalize($typed);

        if (self::looksLikeCode($normalized)) {
            return Learner::where('learner_code', $normalized)->get();
        }

        $tail = strtoupper(preg_replace('/\s+/', '', $typed));

        if (! preg_match('/^[0-9A-Z]{5}$/', $tail)) {
            return collect();
        }

        return Learner::where('learner_code', 'like', 'TB%'.$tail)->get();
    }
}
