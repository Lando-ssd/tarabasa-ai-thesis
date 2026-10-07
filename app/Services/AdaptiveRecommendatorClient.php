<?php

namespace App\Services;

use App\Models\ServiceFailure;
use App\Support\ServiceReply;
use App\Support\ServiceWake;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Real integration with the teammate's deployed Adaptive_Recommendator
 * service (github.com/BldZeuz/Adaptive_Recommendator) — a completely
 * stateless, deterministic (no LLM) decision service: it computes which
 * MATATAG subdomain/difficulty a Learner should practice next (v2; v1 was keyed by the
 * three grouped competencies and is no longer accepted), but stores no
 * student data itself at all (confirmed by reading its real source
 * directly, not just its README). This app is the "main backend" it
 * expects to hold state — every call here must carry the Learner's full
 * current per-subdomain state and recent history, and the response's
 * updated state must be persisted back onto the Learner for next time.
 *
 * Both endpoints require the same X-App-Key header pattern already used
 * by ActivityAiClient. Throws a plain \RuntimeException with a friendly
 * message on any real failure (not configured, unreachable, API error) —
 * every caller MUST catch this and continue without a recommendation
 * rather than blocking the Learner from reading. This integration is a
 * genuine enhancement, never a hard dependency for reading to work.
 */
class AdaptiveRecommendatorClient
{
    /**
     * Called once, right after the first-login diagnostic confirms a
     * Learner's starting level. Returns the decoded InitializeResponse —
     * ['student_id', 'grade', 'subdomain_states', 'next_recommendation', 'policy_version'].
     */
    public function initialize(array $payload): array
    {
        return $this->post('/initialize', $payload);
    }

    /**
     * Called after every real scored Practice reading. Returns the
     * decoded RecommendResponse — ['student_id', 'grade',
     * 'completed_subdomain_update', 'updated_state', 'next_recommendation', 'policy_version'].
     */
    public function recommend(array $payload): array
    {
        return $this->post('/recommend', $payload);
    }

    /**
     * Which contract the deployed service speaks: 1 (three grouped competencies) or 2 (MATATAG subdomains). The team
     * redeployed an older version (1.0.0) than the one this app was first built for (2.0.0) without notice, so the app
     * asks, once in a while, instead of assuming. ADAPTIVE_RECOMMENDER_API (1, 2 or auto) can force it.
     * Null when it cannot be told (the service is asleep, or answers with no version): callers then use version 2,
     * and a service that is really asleep refuses the call anyway.
     */
    public function apiMajor(): ?int
    {
        $forced = (string) config('services.adaptive_recommender.api', 'auto');

        if (in_array($forced, ['1', '2'], true)) {
            return (int) $forced;
        }

        $known = Cache::get('recommender-api-major');

        if ($known !== null) {
            return (int) $known;
        }

        $url = config('services.adaptive_recommender.url');

        if (! $url) {
            return null;
        }

        try {
            $response = Http::timeout(8)->get(rtrim($url, '/').'/health');
            $version = $response->successful() ? (string) $response->json('version') : '';
        } catch (\Throwable) {
            return null;
        }

        if (! preg_match('/^(\d+)\./', $version, $m)) {
            return null;
        }

        Cache::put('recommender-api-major', (int) $m[1], now()->addHours(6));

        return (int) $m[1];
    }

    private function post(string $path, array $payload): array
    {
        $url = config('services.adaptive_recommender.url');
        $key = config('services.adaptive_recommender.key');

        if (! $url || ! $key) {
            throw new \RuntimeException(
                'Adaptive recommendations aren\'t configured yet — ADAPTIVE_RECOMMENDER_URL/ADAPTIVE_RECOMMENDER_KEY are missing from .env.'
            );
        }

        // Same real lesson as the other two integrations: this is a
        // lightweight deterministic computation with no ML inference, but
        // it's still a Render free-tier service that can take well over
        // 30s (a real cold health-check start measured at ~55s during
        // testing) before it even starts processing the request.
        //
        // Longer than the HTTP timeout below (90s) so a slow call is caught
        // as a ConnectionException, not killed by PHP's fatal time limit.
        set_time_limit(120);

        try {
            $response = Http::withHeaders(['X-App-Key' => $key])
                ->timeout(90)
                ->post(rtrim($url, '/').$path, $payload);
        } catch (ConnectionException $e) {
            Log::error('Adaptive Recommendator connection failed', ['path' => $path, 'error' => $e->getMessage()]);

            ServiceFailure::record('recommender', null, 'The adaptive recommender is unreachable right now.', [], $path.' '.$e->getMessage());

            throw new \RuntimeException('The adaptive recommender is unreachable right now.');
        }

        // A real answer proves it is awake; a temporary refusal means it is not (the next reading screen wakes it).
        if (ServiceReply::isTransient($response)) {
            ServiceWake::forget('recommender');
        } else {
            ServiceWake::markAwake('recommender');
        }

        if ($response->failed()) {
            Log::error('Adaptive Recommendator request failed', [
                'path' => $path,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $shown = $this->friendlyApiError($response);
            ServiceFailure::record('recommender', $response, mb_substr($path.': '.$shown, 0, 160), [$response->status()]);

            throw new \RuntimeException($shown);
        }

        return $response->json();
    }

    /**
     * FastAPI's default validation-error shape is {"detail": [...]} (a
     * list of per-field errors) rather than gemini_activity_gen's
     * {message, ...} dict or Reading-api's plain string — handled
     * separately here since a 422 from bad payload shape is a real,
     * plausible failure mode while this integration is being built out.
     */
    private function friendlyApiError(Response $response): string
    {
        $detail = $response->json('detail');

        if (is_string($detail) && $detail !== '') {
            return 'Adaptive recommendation failed: '.$detail;
        }

        if (is_array($detail)) {
            return 'Adaptive recommendation failed: '.json_encode($detail);
        }

        return 'Adaptive recommendation failed (the service returned an unexpected error).';
    }
}
