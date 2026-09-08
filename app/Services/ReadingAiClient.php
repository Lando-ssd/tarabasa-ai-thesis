<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Shared real integration with the deployed Reading-api (Vosk) service —
 * used by both LearnerReadingController (Slice 3) and
 * LearnerDiagnosticController (Slice 4) so this doesn't drift into two
 * copies. No mock, no local scoring logic.
 */
class ReadingAiClient
{
    /**
     * Returns ['unclear' => true] when the audio genuinely couldn't be
     * scored — either Reading-api's own real silent-audio rejection (a
     * 422, discovered during real Slice 3 testing, not assumed from
     * reading the source alone) or a 200 with zero recognized words.
     * Returns ['unclear' => false, 'result' => [...]] with the real
     * decoded response otherwise. Throws ValidationException only for
     * genuine infrastructure problems (not configured, unreachable, any
     * other real error) — those are not "unclear," they're real failures
     * a retry won't fix.
     */
    public function analyze(UploadedFile $audio, string $referenceText, ?float $comprehensionScore = null): array
    {
        $url = config('services.reading_ai.url');

        if (! $url) {
            throw ValidationException::withMessages([
                'audio' => 'The reading checker isn\'t configured yet — READING_AI_URL is missing from .env.',
            ]);
        }

        $filename = 'recording.'.($audio->getClientOriginalExtension() ?: 'webm');

        // Same lesson as Activity Generation: PHP's default 30s script
        // limit is independent of any HTTP client timeout, and a Render
        // free-tier cold start can push a real call past 30s even though
        // Vosk itself is normally fast. Scoped to this action only.
        set_time_limit(120);

        // comprehension_score is Reading-api's own optional form field
        // (confirmed from its real main.py) — it MUST arrive in this same
        // /analyze call, not a follow-up request, since the composite
        // reading_proficiency it feeds is computed synchronously here.
        // Omitted entirely (not sent as an empty value) when the caller
        // has no real score to report, so Reading-api's own "waits for
        // the comprehension activity" fallback still applies correctly.
        $fields = ['reference_text' => $referenceText];
        if ($comprehensionScore !== null) {
            $fields['comprehension_score'] = $comprehensionScore;
        }

        try {
            $response = Http::timeout(120)
                ->attach('file', fopen($audio->getRealPath(), 'r'), $filename)
                ->post(rtrim($url, '/').'/analyze', $fields);
        } catch (ConnectionException $e) {
            Log::error('Reading AI connection failed', ['error' => $e->getMessage()]);

            throw ValidationException::withMessages([
                'audio' => 'The reading checker is unreachable right now. Please try again in a moment.',
            ]);
        }

        if ($response->status() === 422) {
            return ['unclear' => true];
        }

        if ($response->failed()) {
            Log::error('Reading AI request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw ValidationException::withMessages([
                'audio' => $this->friendlyApiError($response),
            ]);
        }

        $result = $response->json();

        if (($result['accuracy']['spoken_word_count'] ?? 0) === 0) {
            return ['unclear' => true];
        }

        return ['unclear' => false, 'result' => $result];
    }

    /**
     * Reading-api's own exception handlers always send a plain string
     * `detail` (confirmed by reading main.py — unlike gemini_activity_gen,
     * there's no nested {message, ...} dict shape here).
     */
    private function friendlyApiError(Response $response): string
    {
        $detail = $response->json('detail');

        if (is_string($detail) && $detail !== '') {
            return 'Reading check failed: '.$detail;
        }

        return 'Reading check failed (the service returned an unexpected error). Please try again.';
    }
}
