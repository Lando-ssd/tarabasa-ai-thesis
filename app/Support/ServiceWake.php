<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Wakes a teammate service that is asleep, and says when it is up.
 *
 * What was found on the live site: the three services run on free hosting that puts them to sleep after about 15
 * minutes of silence. A GET that STAYS CONNECTED (the health page) is held by the host for 20 to 40 seconds and then
 * answered, which is what wakes the service. A POST with a recording or a request body sent to a sleeping service was
 * turned away at once with a plain-text "429 Too Many Requests" or an empty error and did not wake it, and the app's
 * old wake-up ping gave up after 3 to 5 seconds, which very likely cancels the wake-up as well. So the only reliable
 * order is: ask the health page and WAIT for the answer, then send the real request.
 *
 * Nothing here ever throws: the answer is only "did it come up in time", and the caller goes on either way.
 */
class ServiceWake
{
    /** How long a service that answered is trusted to still be awake (the host sleeps it after about 15 minutes). */
    public const KNOWN_AWAKE_MINUTES = 8;

    /** True when this server saw the service answer a few minutes ago, so no health page needs to be asked first. */
    public static function isKnownAwake(string $key): bool
    {
        return Cache::has(self::cacheKey($key));
    }

    public static function markAwake(string $key): void
    {
        Cache::put(self::cacheKey($key), true, now()->addMinutes(self::KNOWN_AWAKE_MINUTES));
    }

    /** Called after a temporary error: what was known about the service is no longer to be trusted. */
    public static function forget(string $key): void
    {
        Cache::forget(self::cacheKey($key));
    }

    /**
     * Asks the health page of $baseUrl until it answers 2xx or $seconds have passed (0 or less: does nothing).
     * Each ask stays connected for up to a minute, which is what wakes a sleeping service.
     */
    public static function await(?string $baseUrl, string $key, int $seconds): bool
    {
        if (! $baseUrl || $seconds <= 0) {
            return false;
        }

        $deadline = microtime(true) + $seconds;

        do {
            try {
                $response = Http::timeout(max(5, (int) min(60, $deadline - microtime(true))))->get(rtrim($baseUrl, '/').'/health');

                if ($response->successful()) {
                    self::markAwake($key);

                    return true;
                }

                Log::warning('A service health check answered with an error', ['service' => $key, 'status' => $response->status(), 'page' => ServiceReply::snippet($response)]);
            } catch (\Throwable $e) {
                Log::warning('A service health check did not answer', ['service' => $key, 'error' => $e->getMessage()]);
            }

            // Never faster than 4 asks a second, whatever the setting: this must not hammer a waking service.
            usleep(max(250000, (int) config('services.retry_pause', 5) * 1000000));
        } while (microtime(true) < $deadline);

        return false;
    }

    private static function cacheKey(string $key): string
    {
        return 'service-awake:'.$key;
    }
}
