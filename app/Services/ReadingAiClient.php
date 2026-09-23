<?php

namespace App\Services;

use App\Models\Activity;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
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
    /** Reading-api v4's own valid MATATAG subdomains per grade (its SUBDOMAINS_BY_GRADE). */
    private const SUBDOMAINS_BY_GRADE = [
        1 => ['Book and Print Knowledge', 'Comprehending and Analyzing Text', 'Phonics and Word Study', 'Phonological Awareness', 'Vocabulary and Word Knowledge'],
        2 => ['Comprehending and Analyzing Text', 'Phonics and Word Study', 'Phonological Awareness', 'Vocabulary and Word Knowledge'],
        3 => ['Comprehending and Analyzing Text', 'Phonics and Word Study', 'Vocabulary and Word Knowledge'],
    ];

    /** Used only when the generator's alignment lookup is unavailable; valid for every grade. */
    private const FALLBACK_SUBDOMAIN = [
        'foundational_reading' => 'Phonics and Word Study',
        'reading_fluency' => 'Comprehending and Analyzing Text',
        'reading_comprehension' => 'Comprehending and Analyzing Text',
    ];

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
    public function analyze(UploadedFile $audio, Activity $activity, ?float $comprehensionScore = null): array
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
        //
        // Kept LONGER than the HTTP timeout below (120s) so a slow call
        // ends as the friendly ConnectionException handled here, not as
        // PHP's uncatchable "Maximum execution time" fatal (a raw 500).
        set_time_limit(150);

        // Reading-api v4 (MATATAG) requires the activity's curriculum context
        // on every call and answers a plain 422 "Field required" without it.
        // Before this was sent, EVERY reading came back as a 422 that the
        // old code read as "unclear audio", so no learner could be scored.
        // Difficulty is activity-demand metadata only: it never changes the
        // accuracy or WCPM formulas (Reading-api's own stated policy).
        $context = $this->activityContext($activity);
        $fields = [
            'grade' => $context['grade'],
            'subdomain' => $context['subdomain'],
            'competency_code' => $context['competency_code'],
            'activity_type' => $activity->activity_type,
            'difficulty' => strtolower((string) $activity->difficulty_tier),
            'activity_id' => (string) $activity->id,
            'attempt_number' => 1,
            'reference_text' => $activity->reference_text ?? $activity->passage_text,
        ];
        // comprehension_score is Reading-api's own optional form field, and it
        // MUST arrive in this same /analyze call (not a follow-up request).
        // Omitted entirely (not sent as an empty value) when the caller has no
        // real score to report. Reading-api v4 only echoes it back; overall
        // proficiency is now the adaptive recommender's job, not this service's.
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

        // A 422 is ambiguous: Reading-api sends it both for genuinely
        // unusable AUDIO (silent, too short, no speech) and for a bad
        // REQUEST (missing or invalid activity context). Only the first is
        // "unclear"; treating the second the same hid a total outage as
        // a microphone problem. Anything else falls through to the real
        // error handling below, which logs it.
        if ($response->status() === 422 && $this->isUnclearAudio($response)) {
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
     * True only for the 422s that describe the AUDIO itself ("Audio is
     * silent or nearly silent.", "Audio is too short...", "No speech-like
     * audio was detected.", "Audio conversion timed out."). A validation
     * error's `detail` is an array (FastAPI) or a message about the
     * activity context, and neither mentions audio.
     */
    private function isUnclearAudio(Response $response): bool
    {
        $detail = $response->json('detail');

        return is_string($detail) && preg_match('/\baudio\b|speech-like|silent/i', $detail) === 1;
    }

    /**
     * Grade + MATATAG subdomain + competency code for an activity, in the
     * shape Reading-api v4 validates. The real code/subdomain come from the
     * generator's own curriculum alignment for this grade + grouped
     * competency (cached a day, since it is fixed curriculum data). If the
     * generator can't be reached, a valid default subdomain is used so a
     * reading is never blocked by this lookup; that fallback is cached only
     * briefly so the real values are picked up as soon as they're available.
     * Reading-api only echoes this context back. It does not change the
     * accuracy, WCPM or prosody numbers.
     *
     * @return array{grade: int, subdomain: string, competency_code: string}
     */
    private function activityContext(Activity $activity): array
    {
        $grade = (int) substr((string) $activity->grade_level, 6);
        $competency = (string) $activity->competency;
        $cacheKey = "matatag_alignment:{$grade}:{$competency}";

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return ['grade' => $grade] + $cached;
        }

        $record = app(ActivityAiClient::class)->alignmentRecord($grade, $competency);

        if ($record !== null && in_array($record['subdomain'], self::SUBDOMAINS_BY_GRADE[$grade] ?? [], true)) {
            $resolved = ['subdomain' => $record['subdomain'], 'competency_code' => $record['code']];
            Cache::put($cacheKey, $resolved, now()->addDay());

            return ['grade' => $grade] + $resolved;
        }

        $fallback = [
            'subdomain' => self::FALLBACK_SUBDOMAIN[$competency] ?? 'Comprehending and Analyzing Text',
            'competency_code' => 'UNMAPPED',
        ];
        Cache::put($cacheKey, $fallback, now()->addMinutes(5));

        return ['grade' => $grade] + $fallback;
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
