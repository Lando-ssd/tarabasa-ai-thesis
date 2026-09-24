<?php

namespace App\Jobs;

use App\Models\Activity;
use App\Models\ActivityGeneration;
use App\Services\ActivityAiClient;
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

        $teacher = $generation->teacher;

        if ($teacher->free_generation_credits_remaining <= 0) {
            $this->finish($generation, ActivityGeneration::FAILED, "You're out of free generation credits.");

            return;
        }

        $wanted = $generation->levels;

        try {
            $data = $activityAi->generateBundle([
                'grade' => (int) substr($generation->grade_level, 6),
                'competency' => $generation->competency,
                'activity_type' => $generation->activity_type,
                'variants_per_level' => max($wanted),
                'topic' => $generation->topic,
                // What the teacher moved to another level before goes to the generator with the
                // notes it already accepts, so it can calibrate. Only the teacher's own words are
                // saved on the activities.
                'teacher_notes' => $this->notesWithCalibration(
                    $generation->teacher_notes,
                    Activity::calibrationNote($teacher->id, $generation->grade_level, $generation->activity_type),
                ),
            ], self::AI_WAIT_SECONDS);
        } catch (\RuntimeException $e) {
            $this->finish($generation, ActivityGeneration::FAILED, $e->getMessage().' Nothing was charged.');

            return;
        }

        $competencies = config('activity_competencies.competencies');

        $created = DB::transaction(function () use ($data, $generation, $teacher, $wanted, $competencies) {
            $rows = Activity::createManyFromBundle($data, [
                'created_by_teacher_id' => $teacher->id,
                'grade_level' => $generation->grade_level,
                'competency' => $generation->competency,
                'competency_label' => $data['competency_label'] ?? $competencies[$generation->competency]['label'],
                'activity_type' => $generation->activity_type,
                'topic' => $generation->topic,
                'teacher_notes' => $generation->teacher_notes,
            ], $wanted);

            $teacher->decrement('free_generation_credits_remaining');

            return $rows;
        });

        $count = $created->count();

        $this->finish(
            $generation,
            ActivityGeneration::DONE,
            $count.' '.Str::plural('draft', $count)." added to To review: {$generation->summary()}.",
            $count,
        );
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

    /** The teacher's own notes first, then the calibration note, kept inside the service's limit. */
    private function notesWithCalibration(?string $notes, ?string $calibration): ?string
    {
        $notes = trim((string) $notes);

        if ($calibration !== null) {
            $room = 1000 - strlen($notes) - ($notes === '' ? 0 : 1);
            $calibration = $room > 40 ? Str::limit($calibration, $room, '') : null;
        }

        $combined = trim($notes.($notes !== '' && $calibration ? "\n" : '').($calibration ?? ''));

        return $combined === '' ? null : $combined;
    }
}
