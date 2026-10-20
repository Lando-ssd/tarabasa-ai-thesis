<?php

namespace App\Jobs;

use App\Models\Activity;
use App\Models\ActivityGeneration;
use App\Services\ActivityAiClient;
use App\Support\GenerationBasis;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Writes the activities of one ActivityGeneration in the background.
 *
 * The AI service writes one text at a time and always writes all three levels, so a request takes
 * from about a minute to several minutes, which is too long to hold a web request open (the
 * browser, the host's proxy and PHP itself all give up somewhere along the way). The request is
 * saved by ActivityController::generate, this job does the slow part, and the Activities page
 * follows the status.
 *
 * A credit is charged only when the activities have been saved, so a failed or timed out request
 * never costs one.
 */
class GenerateActivitiesJob implements ShouldQueue
{
    use Queueable;

    /** The worker gives up on a job after this long. Longer than the HTTP wait below. */
    public int $timeout = 880;

    /** Never run twice: a second run would charge and write the same request again. */
    public int $tries = 1;

    /** How long we wait for the AI service. The longest real request takes about 7 minutes. */
    private const AI_WAIT_SECONDS = 800;

    public function __construct(public int $generationId) {}

    public function handle(ActivityAiClient $activityAi): void
    {
        // When no worker is running, the page that is watching the request runs this job itself, as a web request with PHP's usual
        // 30 second limit. Waiting for a sleeping generator to wake (below) can alone take longer, and PHP then killed the request
        // and left it on "Running" for ever. The limit must be longer than every wait in here (the same rule the AI clients follow).
        set_time_limit(self::AI_WAIT_SECONDS + 300);

        $generation = ActivityGeneration::with('teacher')->find($this->generationId);

        if (! $generation) {
            return;
        }

        // Only one runner may take a request: the background worker, or the page that is watching
        // it if the worker is not running. Whoever gets here second finds it already taken.
        $taken = ActivityGeneration::where('id', $generation->id)
            ->where('status', ActivityGeneration::QUEUED)
            ->update(['status' => ActivityGeneration::RUNNING, 'started_at' => now()]);

        if (! $taken) {
            return;
        }

        // A fatal error (the time limit, memory) skips failed() below, so if this script ends while the request is still Running, close it
        // as Failed instead of leaving the teacher looking at "Writing..." until the 20 minute safety net.
        register_shutdown_function([self::class, 'closeIfStillRunning'], $generation->id);

        $teacher = $generation->teacher;

        if ($teacher->free_generation_credits_remaining <= 0) {
            $this->finish($generation, ActivityGeneration::FAILED, "You're out of free generation credits.");

            return;
        }

        $wanted = $generation->levels;

        // The generator sleeps on free hosting. Nobody is watching a screen in here, so wait (up to
        // about 100 seconds) until it answers its health page before the real request, instead of
        // sending that request at a service that is still starting.
        $activityAi->awaitReady((int) config('services.activity_ai.ready_wait', 100));

        try {
            $data = $activityAi->generateBundle([
                'grade' => (int) substr($generation->grade_level, 6),
                'competency' => $generation->competency,
                'activity_type' => $generation->activity_type,
                'variants_per_level' => max($wanted),
                'topic' => $generation->topic,
                // The notes field is the one channel the generator reads: the teacher's own words, then
                // what the class's real readings say (when the request was written for a class), then
                // what the teacher moved to another level before, so it can calibrate. Only the
                // teacher's own words are saved as the activities' notes; the class basis is saved
                // beside them as `generation_basis`, so the reasons stay visible.
                'teacher_notes' => GenerationBasis::composeNotes([
                    $generation->teacher_notes,
                    $generation->basis['note'] ?? null,
                    Activity::calibrationNote($teacher->id, $generation->grade_level, $generation->activity_type),
                ]),
            ], self::AI_WAIT_SECONDS);
        } catch (\RuntimeException $e) {
            $this->finish($generation, ActivityGeneration::FAILED, $e->getMessage().' Nothing was charged.');

            return;
        }

        $competencies = config('activity_competencies.competencies');

        $created = DB::transaction(function () use ($data, $generation, $teacher, $wanted, $competencies) {
            // The credit is taken first and only if one is left, in one step: the check at the top
            // can be out of date by now, and a credit must never go below zero or be spent twice.
            $charged = \App\Models\Teacher::whereKey($teacher->id)
                ->where('free_generation_credits_remaining', '>', 0)
                ->decrement('free_generation_credits_remaining');

            if ($charged === 0) {
                return null;
            }

            $rows = Activity::createManyFromBundle($data, [
                'created_by_teacher_id' => $teacher->id,
                'grade_level' => $generation->grade_level,
                'competency' => $generation->competency,
                'competency_label' => $data['competency_label'] ?? $competencies[$generation->competency]['label'],
                'activity_type' => $generation->activity_type,
                'topic' => $generation->topic,
                'teacher_notes' => $generation->teacher_notes,
                'generation_basis' => ($generation->basis['enough'] ?? false)
                    ? ['className' => $generation->basis['className'], 'classId' => $generation->class_id, 'lines' => $generation->basis['lines']]
                    : null,
            ], $wanted);

            return $rows;
        });

        if ($created === null) {
            $this->finish($generation, ActivityGeneration::FAILED, "You're out of free generation credits. Nothing was charged.");

            return;
        }

        $count = $created->count();

        $this->finish(
            $generation,
            ActivityGeneration::DONE,
            $count.' '.Str::plural('draft', $count)." added to To review: {$generation->summary()}.",
            $count,
        );
    }

    /** Run when the script ends: a request still marked Running at that point was cut off, so say so and stop the waiting. */
    public static function closeIfStillRunning(int $generationId): void
    {
        try {
            ActivityGeneration::where('id', $generationId)
                ->where('status', ActivityGeneration::RUNNING)
                ->update([
                    'status' => ActivityGeneration::FAILED,
                    'message' => 'The request stopped before it finished. Nothing was charged. Please try again.',
                    'finished_at' => now(),
                ]);
        } catch (Throwable) {
            // The database may already be closed while the process shuts down. The 20 minute safety net still applies.
        }
    }

    /** The worker calls this when the job dies (timed out, or an error nothing caught). */
    public function failed(?Throwable $e): void
    {
        Log::error('Activity generation job failed', ['generation' => $this->generationId, 'error' => $e?->getMessage()]);

        ActivityGeneration::where('id', $this->generationId)
            ->whereIn('status', [ActivityGeneration::QUEUED, ActivityGeneration::RUNNING])
            ->update([
                'status' => ActivityGeneration::FAILED,
                'message' => 'Something went wrong while the AI was writing. Nothing was charged. Please try again.',
                'finished_at' => now(),
            ]);
    }

    private function finish(ActivityGeneration $generation, string $status, string $message, int $count = 0): void
    {
        $generation->update([
            'status' => $status,
            'message' => $message,
            'created_count' => $count,
            'finished_at' => now(),
        ]);
    }
}
