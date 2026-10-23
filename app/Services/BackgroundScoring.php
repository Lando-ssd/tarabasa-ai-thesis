<?php

namespace App\Services;

use App\Jobs\ScoreRecordingJob;
use App\Models\Activity;
use App\Models\Learner;
use App\Models\ScoringJob;
use App\Support\OutcomeCodec;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Scores a child's recording in the background, so a web worker is never held while the speech service thinks.
 *
 * The flow, for the four places a child records (a scored reading, a free practice try, a Bookshelf re-read and the
 * first reading check):
 *   1. The page sends the recording (submit): it is saved to a temporary file with one ScoringJob row, a background
 *      worker is asked to score it, and the answer comes back at once with where to ask how it is going.
 *   2. The page asks the status address every couple of seconds. Nothing is held open between asks.
 *   3. A worker (run) scores it with the SAME service methods the old in-request code called, keeps the result, and
 *      deletes the recording.
 *   4. The page opens the result address, which draws the very same screen the old code drew straight from the post.
 *
 * Why not just score inside the request, as before: a class pressing "done" together held every web worker (four on
 * the free host) for 5 to 45 seconds each, the host's health check went unanswered, and everybody got a 502.
 *
 * A browser that cannot send in the background (no fetch) still posts the form the old way and is scored inside the
 * request. Nothing about the scoring itself, or what it saves, changed: only where it runs.
 */
class BackgroundScoring
{
    private const HEARTBEAT_KEY = 'scoring-worker-seen';

    /** A worker that has not been seen for this long is taken to be gone (a long job leaves gaps of a minute or so). */
    private const HEARTBEAT_TTL = 150;

    /** @var array<string,true> jobs this process is scoring right now (closed as failed if the process dies on them) */
    private static array $running = [];

    private static bool $hooked = false;

    public function __construct(
        private LearnerReadingService $reading,
        private LearnerDiagnosticService $diagnostic,
        private ReadingAiClient $readingAi,
    ) {}

    // ------------------------------------------------------------------ taking a recording in

    /**
     * Save the recording and start scoring it. Answers right away with the job's status; $audio has already passed
     * validation and the caller has already checked that the child may read this activity.
     *
     * @param  array<string,mixed>  $params  what the scoring needs besides the audio (the quiz answers of a reading)
     */
    public function submit(Request $request, string $kind, Learner $learner, Activity $activity, UploadedFile $audio, array $params = []): JsonResponse
    {
        $key = (string) ($request->input('client_key') ?: Str::uuid());

        // The same recording sent again (a lost answer, a double tap, "Send Again"): hand back the job it already has,
        // so it is never scored, and paid in points, twice. A job that FAILED is the exception: Send Again retries it.
        $existing = ScoringJob::where('learner_id', $learner->id)->where('client_key', $key)->first();
        if ($existing && $existing->status === ScoringJob::FAILED) {
            $existing->delete();
            $existing = null;
        }
        if ($existing) {
            return $this->respond($this->refresh($existing));
        }

        // A different recording while one for this same activity is still being scored (the page was left and
        // opened again): that reading is already counting, so show that one instead of scoring a second.
        $active = ScoringJob::active()
            ->where('learner_id', $learner->id)
            ->where('kind', $kind)
            ->where('activity_id', $activity->id)
            ->first();
        if ($active) {
            return $this->respond($this->refresh($active));
        }

        File::ensureDirectoryExists(ScoringJob::audioFolder());

        $id = (string) Str::uuid();
        $extension = preg_replace('/[^a-z0-9]/', '', strtolower((string) ($audio->getClientOriginalExtension() ?: $audio->guessExtension() ?: 'webm')));
        $extension = $extension !== '' ? substr($extension, 0, 8) : 'webm';
        $mime = (string) ($audio->getClientMimeType() ?: 'audio/webm');
        $file = $id.'.'.$extension;

        $audio->move(ScoringJob::audioFolder(), $file);

        try {
            $job = ScoringJob::create([
                'id' => $id,
                'learner_id' => $learner->id,
                'kind' => $kind,
                'activity_id' => $activity->id,
                'client_key' => $key,
                'status' => ScoringJob::QUEUED,
                'audio_path' => $file,
                'audio_mime' => substr($mime, 0, 80),
                'audio_name' => 'recording.'.$extension,
                'params' => $params ?: null,
            ]);
        } catch (QueryException $e) {
            // Two sends of the same recording arrived at the same moment and the other one won: use its job.
            File::delete(ScoringJob::audioFolder().DIRECTORY_SEPARATOR.$file);
            $winner = ScoringJob::where('learner_id', $learner->id)->where('client_key', $key)->first();

            if ($winner) {
                return $this->respond($this->refresh($winner));
            }

            throw $e;
        }

        $this->prune();

        // With a real queue this only saves a row for a worker to find. With the "sync" queue (some local setups and the tests)
        // it scores right here, which is the old behaviour, and the answer below is already "done".
        ScoreRecordingJob::dispatch($job->id);

        return $this->respond($job->fresh());
    }

    private function respond(ScoringJob $job): JsonResponse
    {
        return response()->json($this->payload($job), $job->status === ScoringJob::DONE || $job->status === ScoringJob::FAILED ? 200 : 202);
    }

    /** What the child's page is told: where to ask next, and where the result is once it is done. */
    public function payload(ScoringJob $job): array
    {
        $payload = [
            'id' => $job->id,
            'status' => $job->status,
            'statusUrl' => route('learner.scoring.status', $job->id),
        ];

        if ($job->status === ScoringJob::DONE) {
            $payload['resultUrl'] = route('learner.scoring.result', $job->id);
        }

        if ($job->status === ScoringJob::FAILED) {
            $payload['message'] = $job->error ?: 'Something went wrong while checking your reading. Please try again.';
        }

        return $payload;
    }

    // ------------------------------------------------------------------ asking how it is going

    /**
     * Called each time the page asks. Gives up on a job nobody finished (its worker or the whole server was lost),
     * and, only when NO background worker has been seen for a while, scores a waiting job right here so the app still
     * works where no worker runs (a local setup). It never does that while a worker is alive: with many children
     * waiting, scoring inside their status requests would hold the web workers again, which is what this exists to stop.
     */
    public function refresh(ScoringJob $job): ScoringJob
    {
        if ($job->isStale()) {
            $job->fail('That took too long. Your reading may not have been counted. Please tap Send Again.');

            return $job;
        }

        if ($job->status === ScoringJob::QUEUED
            && $job->created_at->lt(now()->subSeconds((int) config('scoring.inline_after', 12)))
            && ! self::workerSeen()) {
            $this->run($job->id, inline: true);

            return $job->fresh() ?? $job;
        }

        return $job;
    }

    // ------------------------------------------------------------------ scoring it

    /**
     * Score one job: the background worker's whole job, and the fallback the status request uses when no worker runs.
     * Taking the job is one atomic update, so only one of them ever scores it. Never throws: the outcome (or why it
     * failed) is written on the row, and the recording is deleted either way.
     */
    public function run(string $jobId, bool $inline = false): void
    {
        $job = ScoringJob::find($jobId);

        if (! $job || ! $job->claim()) {
            return;
        }

        self::heartbeat();

        // This may be a web request that the child's page abandoned; the scoring must still finish and be recorded once.
        ignore_user_abort(true);
        set_time_limit((int) config('scoring.job_hold', 90) + 150);
        self::watch($job->id);

        // A background worker is not one of the few web workers, so it may wait on the speech service longer than a
        // web request is allowed to (services.reading_ai.max_hold). Put back afterwards: the worker lives on.
        $previousHold = config('services.reading_ai.max_hold');
        if (! $inline) {
            config(['services.reading_ai.max_hold' => (int) config('scoring.job_hold', 90)]);
        }

        try {
            $job->complete(OutcomeCodec::encode($this->execute($job)));
        } catch (ValidationException $e) {
            // The speech client's own plain-language reasons ("waking up", "busy", "not configured").
            $job->fail($e->validator->errors()->first() ?: 'Something went wrong while checking your reading. Please try again.');
        } catch (Throwable $e) {
            report($e);
            $job->fail('Something went wrong while checking your reading. Please try again.');
        } finally {
            config(['services.reading_ai.max_hold' => $previousHold]);
            $job->discardAudio();
            unset(self::$running[$job->id]);
            self::heartbeat();
        }
    }

    /** The same service call the old in-request code made for this kind of recording. */
    private function execute(ScoringJob $job): array
    {
        $learner = Learner::findOrFail($job->learner_id);
        $path = $job->audioFullPath();

        if (! $path) {
            throw ValidationException::withMessages(['audio' => 'We lost your recording on the way. Please read it again.']);
        }

        $audio = new UploadedFile($path, $job->audio_name ?: 'recording.webm', $job->audio_mime ?: 'audio/webm', null, true);
        $activity = Activity::find($job->activity_id);

        if (! $activity) {
            throw ValidationException::withMessages(['audio' => 'That activity is not here any more.']);
        }

        return match ($job->kind) {
            ScoringJob::READING => $this->reading->recordAttempt($learner, $activity, $audio, $job->params['answers'] ?? null, $this->readingAi),
            ScoringJob::PRACTICE => $this->reading->recordPractice($learner, $activity, $audio, $this->readingAi),
            ScoringJob::REREAD => $this->reading->recordFreeReattempt($activity, $audio, $this->readingAi),
            ScoringJob::DIAGNOSTIC => $this->executeDiagnostic($learner, $activity, $audio),
            default => throw new \LogicException("Unknown scoring kind {$job->kind}"),
        };
    }

    private function executeDiagnostic(Learner $learner, Activity $activity, UploadedFile $audio): array
    {
        $state = $this->diagnostic->diagnosticState($learner);

        if ($state === null) {
            throw ValidationException::withMessages(['audio' => 'The reading check was not found. Please go back and start it again.']);
        }

        // The page sent this recording for one passage; if the check has moved on since, this reading was already counted.
        if ($this->diagnostic->currentActivityId($state) !== $activity->id) {
            throw ValidationException::withMessages(['audio' => 'That reading was already counted. Please go on to the next one.']);
        }

        return $this->diagnostic->recordAttempt($learner, $state, $activity, $audio, $this->readingAi);
    }

    // ------------------------------------------------------------------ is a worker alive

    /** Written by every scoring worker while it waits for work and as it starts and ends a job. */
    public static function heartbeat(): void
    {
        Cache::put(self::HEARTBEAT_KEY, time(), self::HEARTBEAT_TTL);
    }

    public static function workerSeen(): bool
    {
        return Cache::has(self::HEARTBEAT_KEY);
    }

    /** Registers the "worker is alive" signal for the scoring queue. Called once at boot. */
    public static function listen(): void
    {
        $last = 0;

        Event::listen(Looping::class, function (Looping $event) use (&$last) {
            // Idle workers loop every couple of seconds; write the signal only now and then to spare the database.
            if (str_contains($event->queue, 'scoring') && time() - $last >= 15) {
                $last = time();
                self::heartbeat();
            }
        });
    }

    // ------------------------------------------------------------------ safety nets

    /** If this process dies in the middle of a job (out of memory, the host stopping it), close the job as failed instead of leaving it "running". */
    private static function watch(string $jobId): void
    {
        self::$running[$jobId] = true;

        if (self::$hooked) {
            return;
        }

        self::$hooked = true;
        register_shutdown_function(function () {
            foreach (array_keys(self::$running) as $id) {
                $job = ScoringJob::find($id);

                if ($job && $job->status === ScoringJob::RUNNING) {
                    $job->fail('That took too long. Your reading may not have been counted. Please tap Send Again.');
                }
            }
        });
    }

    /** Old results and any recording file left behind (a worker killed in the middle of a job) are removed now and then. */
    public function prune(bool $always = false): void
    {
        if (! $always && random_int(1, 20) !== 1) {
            return;
        }

        ScoringJob::where('created_at', '<', now()->subHours((int) config('scoring.keep_hours', 24)))->delete();

        // A finished job whose recording could not be deleted at the time.
        ScoringJob::whereNotNull('audio_path')->whereIn('status', [ScoringJob::DONE, ScoringJob::FAILED])->get()->each->discardAudio();

        $folder = ScoringJob::audioFolder();
        if (! File::isDirectory($folder)) {
            return;
        }

        $stale = now()->subSeconds(ScoringJob::staleAfter() + 60)->getTimestamp();
        foreach (File::files($folder) as $file) {
            if ($file->getMTime() < $stale) {
                File::delete($file->getPathname());
            }
        }
    }
}
