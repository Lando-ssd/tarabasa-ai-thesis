<?php

namespace App\Support;

use Illuminate\Support\Facades\URL;

/**
 * Makes our own https address the only address the system uses: for the links it writes (emails, redirects, signed links) and
 * for the host it believes a request was sent to. Nothing taken from the visitor's request can change it.
 *
 * Without this, a stranger could send a request with a forged host header and the system would put that host into a password
 * reset or confirmation email addressed to someone else. The secret token in the link would then go to the stranger's site.
 * (Found on 2026-11-09; see tests/Feature/HostHeaderTest.php.)
 *
 * Two parts work together:
 *  - pin() makes every address we WRITE start with APP_URL;
 *  - App\Http\Middleware\CanonicalHost makes every request we READ look as if it was sent to APP_URL's host, which keeps signed
 *    links (confirmation, unlock) valid and does not depend on how the hosting proxy passes the host along.
 *
 * It only acts on an https address. A plain http address (this computer while developing, the test suite) is left alone, so
 * nothing changes for local work. On the live site APP_URL is the https address of the site, which "Continue with Google" already
 * needs in order to come back to the right place.
 */
class PublicUrl
{
    /** The host (and port, if any) of the pinned address, or null when nothing is pinned. */
    private static ?string $host = null;

    /** Returns true when the address was pinned. */
    public static function pin(?string $appUrl): bool
    {
        $appUrl = is_string($appUrl) ? trim($appUrl) : '';

        // https, then a real host name (letters, digits, dots, dashes, an optional port), then at most a path
        if (! preg_match('#^https://([A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?(:\d{1,5})?)(/.*)?$#', $appUrl, $m)) {
            return false;
        }

        self::$host = strtolower($m[1]);
        URL::forceRootUrl(rtrim($appUrl, '/'));
        URL::forceScheme('https');

        return true;
    }

    /** The pinned host, or null when nothing is pinned. */
    public static function host(): ?string
    {
        return self::$host;
    }

    /** Undoes pin() (used by tests, so one test cannot leak into the next). */
    public static function reset(): void
    {
        self::$host = null;
        URL::forceRootUrl(null);
        URL::forceScheme(null);
    }
}
