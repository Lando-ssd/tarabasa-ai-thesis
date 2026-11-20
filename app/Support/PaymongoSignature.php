<?php

namespace App\Support;

/**
 * Proves that a webhook message really came from PayMongo (App\Services\PaymentService::handleWebhook).
 *
 * As PayMongo's webhook setup page describes it (read on 2026-11-20): the header `Paymongo-Signature` looks like `t=<time>,te=<test signature>,li=<live
 * signature>`; the signed text is the time, a full stop and the RAW request body (not the parsed one); the hash is HMAC with SHA-256 using the webhook's own
 * secret (it starts with `whsk_`); `te` is compared for test mode events and `li` for live mode events; a message that does not match is thrown away.
 * The page calls checking the time against the clock "optional but recommended". It is not done here on purpose: PayMongo tries a failed delivery again up
 * to 12 times with growing gaps and does not promise the intervals, so a late retry of a genuine message would be refused, and a repeated message
 * changes nothing anyway (a payment that is already paid is left alone).
 *
 * This version works with TEST mode only, so only `te` is ever accepted.
 */
class PaymongoSignature
{
    /** @return array{t: string, te: string, li: string} */
    public static function parts(?string $header): array
    {
        $parts = ['t' => '', 'te' => '', 'li' => ''];
        foreach (explode(',', (string) $header) as $piece) {
            [$key, $value] = array_pad(explode('=', trim($piece), 2), 2, '');
            if (isset($parts[$key])) {
                $parts[$key] = $value;
            }
        }

        return $parts;
    }

    /** The signature PayMongo would have made for this time and body. */
    public static function sign(string $timestamp, string $rawBody, string $secret): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);
    }

    /** True only for a test mode signature that matches, compared in constant time. */
    public static function validTest(string $rawBody, ?string $header, ?string $secret): bool
    {
        if ($secret === null || $secret === '') {
            return false;
        }

        $parts = self::parts($header);
        if ($parts['t'] === '' || $parts['te'] === '') {
            return false;
        }

        return hash_equals(self::sign($parts['t'], $rawBody, $secret), $parts['te']);
    }
}
