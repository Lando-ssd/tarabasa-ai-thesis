<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Shared real integration with the deployed gemini_activity_gen service —
 * used by ActivityController (Module 2, Teacher-authored activities) and
 * LearnerDiagnosticController (Sprint 4 Slice 4, system-generated
 * diagnostic passages) so this doesn't drift into two copies. No mock.
 */
class ActivityAiClient
{
    /**
     * Returns the decoded bundle response array on success. Throws a
     * plain \RuntimeException with a friendly message on any real
     * failure (not configured, unreachable, API error) — callers wrap it
     * into their own ValidationException with whatever field key fits
     * their form, since that differs per caller.
     */
    public function generateBundle(array $payload): array
    {
        $url = config('services.activity_ai.url');
        $key = config('services.activity_ai.key');

        if (! $url || ! $key) {
            throw new \RuntimeException(
                'Activity generation isn\'t configured yet — ACTIVITY_AI_URL/ACTIVITY_AI_KEY are missing from .env.'
            );
        }

        // A real Gemini call generating 3 difficulty tiers (each with its
        // own retry-on-validation-failure loop) plus a possible Render
        // free-tier cold start can genuinely exceed PHP's default 30s
        // script limit — confirmed by hitting exactly that in testing.
        //
        // The PHP limit must be LONGER than the HTTP timeout below. When
        // both were 150 the script limit fired first, and PHP's "Maximum
        // execution time exceeded" is an uncatchable fatal error — a raw
        // 500 page (seen on the first-login diagnostic) instead of the
        // friendly "unreachable, try again" message the catch below gives.
        set_time_limit(180);

        try {
            $response = Http::withHeaders(['X-App-Key' => $key])
                ->timeout(150)
                ->post(rtrim($url, '/').'/generate-bundle', $payload);
        } catch (ConnectionException $e) {
            Log::error('Activity AI connection failed', ['error' => $e->getMessage()]);

            throw new \RuntimeException('The activity generator is unreachable right now. Please try again in a moment.');
        }

        if ($response->failed()) {
            Log::error('Activity AI request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException($this->friendlyApiError($response));
        }

        return $response->json();
    }

    /**
     * The first real MATATAG curriculum record (its official code and
     * subdomain) the generator maps a grade + grouped competency onto,
     * from the generator's own GET /matatag/alignment. Reading-api v4
     * requires both on every /analyze call (it validates the subdomain
     * against the grade and echoes both back as the activity context).
     *
     * Returns null on ANY problem (not configured, unreachable, unexpected
     * shape) — never throws — because a reading must never be blocked
     * just because this lookup failed; the caller falls back to a valid
     * default instead. Short timeout for the same reason: this is a quick
     * lookup, not a generation, and a cold Render service shouldn't stall
     * a child who is waiting on their results.
     *
     * @return array{code: string, subdomain: string}|null
     */
    public function alignmentRecord(int $grade, string $competency): ?array
    {
        $url = config('services.activity_ai.url');
        $key = config('services.activity_ai.key');

        if (! $url || ! $key) {
            return null;
        }

        try {
            $response = Http::withHeaders(['X-App-Key' => $key])
                ->timeout(8)
                ->get(rtrim($url, '/').'/matatag/alignment', ['grade' => $grade, 'competency' => $competency]);
        } catch (ConnectionException $e) {
            Log::warning('MATATAG alignment lookup failed', ['error' => $e->getMessage()]);

            return null;
        }

        if ($response->failed()) {
            Log::warning('MATATAG alignment lookup failed', ['status' => $response->status()]);

            return null;
        }

        $record = $response->json('records.0');

        if (! is_array($record) || empty($record['code']) || empty($record['subdomain'])) {
            return null;
        }

        return ['code' => (string) $record['code'], 'subdomain' => (string) $record['subdomain']];
    }

    /**
     * Turns the API's error shapes (plain string detail, or the structured
     * {message, failed_level, gemini_code, ...} dict main.py sends on a
     * Gemini failure) into one clean sentence — never raw JSON in front of
     * a Teacher.
     */
    private function friendlyApiError(Response $response): string
    {
        $detail = $response->json('detail');

        if (is_array($detail) && isset($detail['message'])) {
            return 'Activity generation failed: '.$detail['message'];
        }

        if (is_string($detail) && $detail !== '') {
            return 'Activity generation failed: '.$detail;
        }

        return 'Activity generation failed (the service returned an unexpected error). Please try again.';
    }
}
