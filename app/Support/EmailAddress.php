<?php

namespace App\Support;

/**
 * Small helpers for the addresses people type when they sign up.
 *
 * Whether a mailbox really exists can only be proven by sending to it and seeing the person open the link, which is
 * why sign in waits for that. These helpers only catch the mistakes that are obvious before anything is sent.
 */
class EmailAddress
{
    /** Misspelled domains people type all the time, and what they meant. */
    private const TYPOS = [
        'gmial.com' => 'gmail.com', 'gmai.com' => 'gmail.com', 'gamil.com' => 'gmail.com', 'gmal.com' => 'gmail.com',
        'gnail.com' => 'gmail.com', 'gmail.co' => 'gmail.com', 'gmail.con' => 'gmail.com', 'gmail.comm' => 'gmail.com',
        'gmail.cm' => 'gmail.com', 'gmaill.com' => 'gmail.com', 'gmeil.com' => 'gmail.com',
        'yahooo.com' => 'yahoo.com', 'yaho.com' => 'yahoo.com', 'yahoo.con' => 'yahoo.com', 'yahoo.comm' => 'yahoo.com',
        'hotmial.com' => 'hotmail.com', 'hotmal.com' => 'hotmail.com', 'hotmail.con' => 'hotmail.com',
        'outlok.com' => 'outlook.com', 'outlook.con' => 'outlook.com',
    ];

    /** The address the person most likely meant, or null when nothing looks mistyped. */
    public static function suggestion(string $email): ?string
    {
        $email = strtolower(trim($email));
        $at = strrpos($email, '@');

        if ($at === false) {
            return null;
        }

        $fixed = self::TYPOS[substr($email, $at + 1)] ?? null;

        return $fixed ? substr($email, 0, $at + 1).$fixed : null;
    }

    /** "pinky@gmail.com" becomes "pi***@gmail.com": enough to recognise your own address, not enough to harvest it. */
    public static function mask(string $email): string
    {
        $at = strrpos($email, '@');

        if ($at === false) {
            return $email;
        }

        $local = substr($email, 0, $at);
        $shown = mb_substr($local, 0, mb_strlen($local) > 3 ? 2 : 1);

        return $shown.'***'.substr($email, $at);
    }
}
