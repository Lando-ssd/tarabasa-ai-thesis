<?php

namespace App\Support;

use Illuminate\Http\Client\Response;

/**
 * Reads the answer of one of the three teammate services (activity generator, reading checker,
 * adaptive recommender) when it is not a success.
 *
 * Why this exists: those services answer in several shapes. A message they wrote themselves comes as
 * {"detail": "text"} or {"detail": {"message": ...}}, a rejected request (HTTP 422) as a LIST of
 * problems, a rate limit as {"error": "..."}, and the hosting in front of them (Render) answers a
 * sleeping, restarting or overloaded service with a plain web page. The old code only understood the
 * first two, so every other answer became the same vague "unexpected error" and nobody could tell
 * which one it was. This class turns every shape into a plain sentence that carries the HTTP status.
 */
class ServiceReply
{
    /**
     * Statuses that mean "try again in a moment" rather than "this request is wrong": the free hosting
     * answers these while a service is waking up, restarting, or busy. A real mistake in the request
     * (400, 401, 403, 404, 413, 422) is never in this list, because trying it again cannot help.
     */
    public const TRANSIENT = [408, 425, 429, 502, 503, 504];

    public static function isTransient(Response $response): bool
    {
        if (! in_array($response->status(), self::TRANSIENT, true)) {
            return false;
        }

        // A 502, 503 or 504 that the SERVICE ITSELF wrote ({"detail": ...}) is its real answer, not the
        // hosting's "come back in a moment" page: the generator uses 502 when the AI model failed even
        // after its own retries. That explanation is shown as it is and the request is not repeated.
        $json = $response->json();

        if (in_array($response->status(), [502, 503, 504], true) && is_array($json) && array_key_exists('detail', $json)) {
            return false;
        }

        return true;
    }

    /**
     * The service's own words, when it gave any, in one line. Null when the body had nothing readable
     * (an empty answer, or a web page from the hosting).
     */
    public static function detail(Response $response): ?string
    {
        $json = $response->json();

        if (is_array($json)) {
            $detail = $json['detail'] ?? null;

            // {"detail": "text"}
            if (is_string($detail) && trim($detail) !== '') {
                return trim($detail);
            }

            // {"detail": {"message": "text", ...}} (the generator's own Gemini failure shape)
            if (is_array($detail) && isset($detail['message']) && is_string($detail['message'])) {
                return trim($detail['message']);
            }

            // {"detail": [{"loc": ["body", "field"], "msg": "text"}]} (a rejected request, HTTP 422)
            if (is_array($detail) && isset($detail[0]) && is_array($detail[0])) {
                $first = $detail[0];
                $where = isset($first['loc']) && is_array($first['loc'])
                    ? implode('.', array_filter(array_map('strval', array_slice($first['loc'], 1))))
                    : '';
                $what = (string) ($first['msg'] ?? 'not accepted');

                return trim(($where !== '' ? $where.': ' : '').$what);
            }

            // {"error": "Rate limit exceeded ..."} and {"message": "..."}
            foreach (['error', 'message'] as $key) {
                if (isset($json[$key]) && is_string($json[$key]) && trim($json[$key]) !== '') {
                    return trim($json[$key]);
                }
            }
        }

        return null;
    }

    /**
     * A short, safe description of a body that is NOT json (a web page from the hosting, for example),
     * for the log only: tags removed, spaces folded, cut to 160 characters.
     */
    public static function snippet(Response $response): string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', strip_tags(str_replace('<', ' <', $response->body()))));

        return mb_substr($text, 0, 160);
    }

    /**
     * One plain sentence for the screen. $what is "Activity generation" or "Reading check".
     * The HTTP status is always included, so a person (or the team) can tell a 503 from a 422 from a
     * 429 without opening any log.
     */
    public static function message(Response $response, string $what, string $waking): string
    {
        $status = $response->status();
        $detail = self::detail($response);

        if (self::isTransient($response)) {
            return "{$waking} (error {$status}). Please try again in a minute.";
        }

        if ($detail !== null) {
            return "{$what} failed: {$detail}".(preg_match('/[.!?]$/', $detail) ? '' : '.')." (error {$status})";
        }

        return "{$what} failed (the service answered with error {$status} and no explanation). Please try again.";
    }
}
