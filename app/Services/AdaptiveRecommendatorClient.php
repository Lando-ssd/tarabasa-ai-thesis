<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Real integration with the teammate's deployed Adaptive_Recommendator
 * service (github.com/BldZeuz/Adaptive_Recommendator) — a completely
 * stateless, deterministic (no LLM) decision service: it computes which
 * competency/difficulty a Learner should practice next, but stores no
 * student data itself at all (confirmed by reading its real source
 * directly, not just its README). This app is the "main backend" it
 * expects to hold state — every call here must carry the Learner's full
 * current competency state and recent history, and the response's
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
     * ['student_id', 'grade', 'competency_states', 'next_recommendation'].
     */
    public function initialize(array $payload): array
    {
        return $this->post('/initialize', $payload);
    }

    /**
     * Called after every real scored Practice reading. Returns the
     * decoded RecommendResponse — ['student_id', 'grade',
     * 'completed_competency_update', 'updated_state', 'next_recommendation'].
     */
    public function recommend(array $payload): array
    {
        return $this->post('/recommend', $payload);
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
        set_time_limit(90);

        try {
            $response = Http::withHeaders(['X-App-Key' => $key])
                ->timeout(90)
                ->post(rtrim($url, '/').$path, $payload);
        } catch (ConnectionException $e) {
            Log::error('Adaptive Recommendator connection failed', ['path' => $path, 'error' => $e->getMessage()]);

            throw new \RuntimeException('The adaptive recommender is unreachable right now.');
        }

        if ($response->failed()) {
            Log::error('Adaptive Recommendator request failed', [
                'path' => $path,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException($this->friendlyApiError($response));
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
