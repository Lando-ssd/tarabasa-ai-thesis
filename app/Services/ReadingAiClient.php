<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ServiceFailure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use App\Jobs\WakeServiceJob;
use App\Support\ServiceReply;
use App\Support\ServiceWake;
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
    /** How many times a temporary hosting error (502, 503, 504, 429) is asked again before giving up. */
    private const TEMPORARY_ERROR_TRIES = 4;

    /** Seconds to wait before the 2nd try, longer each time (5, 10, 15; a 429 slow down waits twice as long, 10, 20, 30). */
    private const TEMPORARY_ERROR_PAUSE = 5;

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
        set_time_limit(400);

        // Reading-api v4 (MATATAG) requires the activity's curriculum context
        // on every call and answers a plain 422 "Field required" without it.
        // Before this was sent, EVERY reading came back as a 422 that the
        // old code read as "unclear audio", so no learner could be scored.
        // Difficulty is activity-demand metadata only: it never changes the
        // accuracy or WCPM formulas (Reading-api's own stated policy).
        $context = app(MatatagAlignmentResolver::class)->contextFor($activity);
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

        // A sleeping, restarting or busy service on free hosting answers with a quick 502, 503, 504 or
        // 429 (a plain web page). The child has just read a whole passage, so asking again a few
        // seconds later is far kinder than sending them back to read it again. A real problem with the
        // request (422, 413 and so on) is never repeated.
        $response = null;
        $trail = [];

        // A sleeping checker turns a recording away at once ("429 Too Many Requests", or an empty error) and the
        // recording does NOT wake it: only a request that stays connected does (see ServiceWake). So unless this
        // server saw the checker answer a few minutes ago, the health page is asked first and waited for.
        $wait = (int) config('services.reading_ai.ready_wait', 100);

        if ($wait > 0 && ! ServiceWake::isKnownAwake('reader')) {
            $this->awaitReady($wait);
        }

        for ($try = 1; $try <= self::TEMPORARY_ERROR_TRIES; $try++) {
            try {
                // The recording is opened again for every try: the first one read it to the end.
                $response = Http::timeout(120)
                    ->attach('file', fopen($audio->getRealPath(), 'r'), $filename)
                    ->post(rtrim($url, '/').'/analyze', $fields);
            } catch (ConnectionException $e) {
                Log::error('Reading AI connection failed', ['error' => $e->getMessage()]);

                $shown = 'The reading checker is unreachable right now. Please try again in a moment.';
                ServiceFailure::record('reader', null, $shown, $trail, $e->getMessage());

                throw ValidationException::withMessages(['audio' => $shown]);
            }

            if ($response->failed()) {
                $trail[] = $response->status();
            }

            if (! $response->failed() || ! ServiceReply::isTransient($response) || $try === self::TEMPORARY_ERROR_TRIES) {
                break;
            }

            Log::warning('Reading AI answered with a temporary error, trying again', [
                'status' => $response->status(),
                'attempt' => $try,
                'page' => ServiceReply::snippet($response),
            ]);

            // Whatever was known about the checker is no longer true. Ask its health page and wait for the answer
            // (this is what wakes it); only when that does not work is there a plain pause before the next try.
            ServiceWake::forget('reader');

            if ($wait > 0 && $this->awaitReady(min($wait, 60))) {
                continue;
            }

            sleep(ServiceReply::pause($response, $try, (int) config('services.retry_pause', self::TEMPORARY_ERROR_PAUSE)));
        }

        // Any real answer (even a rejection of the recording) proves the checker is awake.
        if (! ServiceReply::isTransient($response)) {
            ServiceWake::markAwake('reader');
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
                'host' => parse_url($url, PHP_URL_HOST),
                'content_type' => $response->header('Content-Type'),
                'body' => mb_substr($response->body(), 0, 2000),
            ]);

            $shown = $this->friendlyApiError($response);
            ServiceFailure::record('reader', $response, $shown, $trail);

            throw ValidationException::withMessages(['audio' => $shown]);
        }

        $result = $response->json();

        if (($result['accuracy']['spoken_word_count'] ?? 0) === 0) {
            return ['unclear' => true];
        }

        // Words read correctly that the recognizer wrote differently (same sound, spelling variants,
        // numbers, one word written as two) are put right here, once, for every screen.
        return ['unclear' => false, 'result' => \App\Support\SpeechNormalizer::apply($result)];
    }

    /**
     * Wakes the scoring service. It runs on free hosting that goes to sleep when nobody has used it
     * for a while, and the first reading afterwards then waits up to a minute for it to start. A
     * screen where a child is about to read or speak calls this as it opens, so the service is
     * already awake by the time the recording arrives. It does not wait for the answer, and it is
     * remembered for a few minutes so a busy classroom costs one request, not hundreds.
     */
    public function wake(): void
    {
        $url = config('services.reading_ai.url');

        if (! $url || ServiceWake::isKnownAwake('reader') || ! Cache::add('reading-api-wake-requested', true, now()->addMinutes(2))) {
            return;
        }

        // In the background: staying connected to the health page for as long as it takes is what wakes it.
        WakeServiceJob::start('reader');
    }

    /**
     * Asks the checker's health page and waits (up to $seconds) for the answer, which wakes it when it is asleep.
     * Never throws; false when it did not come up in time (the caller sends the recording anyway).
     */
    public function awaitReady(int $seconds = 100): bool
    {
        return ServiceWake::await(config('services.reading_ai.url'), 'reader', $seconds);
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
     * Reading-api's own exception handlers always send a plain string
     * `detail` (confirmed by reading main.py — unlike gemini_activity_gen,
     * there's no nested {message, ...} dict shape here).
     */
    private function friendlyApiError(Response $response): string
    {
        return ServiceReply::message($response, 'Reading check', 'The reading checker is waking up or busy right now');
    }
}
