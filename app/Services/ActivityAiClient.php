<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
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
    public function generateBundle(array $payload, int $timeout = 150, int $attempts = 1): array
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
        set_time_limit($timeout * $attempts + 30);

        try {
            // A request that hangs (the service sometimes never answers) is
            // tried again when the caller allows it, instead of holding a child
            // on a waiting screen for the full timeout.
            $response = Http::withHeaders(['X-App-Key' => $key])
                ->timeout($timeout)
                ->retry($attempts, 500, when: fn ($e) => $e instanceof ConnectionException, throw: false)
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

        $json = $response->json();

        if (! is_array($json)) {
            Log::error('Activity AI answered with something unreadable', ['body' => substr($response->body(), 0, 300)]);

            throw new \RuntimeException('The activity generator sent back something unreadable. Please try again.');
        }

        return $json;
    }

    /**
     * Wake the generator if it is asleep. Its free hosting sleeps when idle and a cold start can
     * take a minute, which on top of a generation can pass the wait we allow. The Generate window
     * calls this the moment it opens, so the service is waking while the teacher fills the form.
     * Never throws and never waits long: the answer does not matter, only that the request lands.
     */
    public function wake(): void
    {
        $url = config('services.activity_ai.url');

        if (! $url) {
            return;
        }

        try {
            Http::timeout(3)->get(rtrim($url, '/').'/health');
        } catch (\Throwable $e) {
            // Expected while it is still waking: the request reached it, which is the point.
        }
    }

    /**
     * Several bundle requests at once, for the first-login reading check
     * (which can need two different kinds of content, each taking several
     * seconds). Same failure behavior as generateBundle(): a plain
     * \RuntimeException with a friendly message if any one of them fails.
     *
     * @param  array<string, array>  $payloads  request bodies, keyed by any label
     * @return array<string, array>  the decoded bundles under the same labels
     */
    public function generateBundles(array $payloads, int $timeout = 150, int $attempts = 1): array
    {
        if (count($payloads) === 1) {
            $label = array_key_first($payloads);

            return [$label => $this->generateBundle($payloads[$label], $timeout, $attempts)];
        }

        $url = config('services.activity_ai.url');
        $key = config('services.activity_ai.key');

        if (! $url || ! $key) {
            throw new \RuntimeException(
                'Activity generation isn\'t configured yet — ACTIVITY_AI_URL/ACTIVITY_AI_KEY are missing from .env.'
            );
        }

        // Longer than the HTTP timeouts below, for the same reason as generateBundle().
        set_time_limit($timeout * $attempts + 30);

        $responses = Http::pool(function (Pool $pool) use ($payloads, $url, $key, $timeout) {
            foreach ($payloads as $label => $payload) {
                $pool->as((string) $label)
                    ->withHeaders(['X-App-Key' => $key])
                    ->timeout($timeout)
                    ->post(rtrim($url, '/').'/generate-bundle', $payload);
            }
        });

        $bundles = [];

        foreach (array_keys($payloads) as $label) {
            $response = $responses[$label] ?? null;

            // A request that could not connect (or hung until the timeout) comes
            // back as the exception itself. Try that one again on its own.
            if (! $response instanceof Response) {
                Log::warning('Activity AI request did not answer', ['error' => $response instanceof \Throwable ? $response->getMessage() : 'no response']);

                if ($attempts > 1) {
                    $bundles[$label] = $this->generateBundle($payloads[$label], $timeout, $attempts - 1);

                    continue;
                }

                throw new \RuntimeException('The activity generator is unreachable right now. Please try again in a moment.');
            }

            if ($response->failed()) {
                Log::error('Activity AI request failed', ['status' => $response->status(), 'body' => $response->body()]);

                throw new \RuntimeException($this->friendlyApiError($response));
            }

            $bundles[$label] = $response->json();
        }

        return $bundles;
    }

    /**
     * The real MATATAG curriculum records (official code + subdomain) the
     * generator maps a grade + grouped competency onto, from the
     * generator's own GET /matatag/alignment. Reading-api v4 and the
     * adaptive recommender both need a valid subdomain and a competency
     * code for every activity.
     *
     * Returns null on ANY problem (not configured, unreachable, unexpected
     * shape) and never throws, because a reading must never be blocked just
     * because this lookup failed; the caller falls back to a valid default.
     * Short timeout for the same reason: this is a quick lookup, not a
     * generation, and a cold Render service shouldn't stall a child who is
     * waiting on their results.
     *
     * @return list<array{code: string, subdomain: string}>|null
     */
    public function alignmentRecords(int $grade, string $competency): ?array
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

        $records = collect($response->json('records', []))
            ->filter(fn ($record) => is_array($record) && ! empty($record['code']) && ! empty($record['subdomain']))
            ->map(fn (array $record) => ['code' => (string) $record['code'], 'subdomain' => (string) $record['subdomain']])
            ->values()
            ->all();

        return $records === [] ? null : $records;
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
