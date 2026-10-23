<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ScoringJob;
use App\Services\BackgroundScoring;
use App\Support\OutcomeCodec;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The two addresses a child's page uses after it has sent a recording (see App\Services\BackgroundScoring):
 * "is it done yet?" (asked every couple of seconds) and "show me the result" (opened once it is done, and safe to reload).
 *
 * Both are only for the child who made the recording. Another child, a guess at an id, or an old id all get "not found".
 */
class ScoringController extends Controller
{
    public function status(Request $request, ScoringJob $job, BackgroundScoring $scoring): JsonResponse
    {
        abort_unless($job->learner_id === $request->user('learner')->id, 404);

        return response()->json($scoring->payload($scoring->refresh($job)))->header('Cache-Control', 'no-store');
    }

    public function result(Request $request, ScoringJob $job, BackgroundScoring $scoring): View|RedirectResponse
    {
        $learner = $request->user('learner');
        abort_unless($job->learner_id === $learner->id, 404);

        $job = $scoring->refresh($job);

        // Opened too early (for example from a bookmark): back to the dashboard, which is always a safe place.
        if ($job->isActive()) {
            return redirect()->route('learner.dashboard');
        }

        abort_unless($job->status === ScoringJob::DONE, 404);

        $outcome = OutcomeCodec::decode($job->outcome);
        $activity = Activity::find($job->activity_id);
        abort_if($outcome === null || $activity === null, 404);

        return match ($job->kind) {
            ScoringJob::READING => app(LearnerReadingController::class)->renderRecording($outcome, $activity),
            ScoringJob::PRACTICE => app(LearnerReadingController::class)->renderPractice($outcome, $activity, $learner),
            ScoringJob::DIAGNOSTIC => app(LearnerDiagnosticController::class)->renderRecording($outcome),
            ScoringJob::REREAD => app(BookshelfController::class)->renderReread($outcome, $activity),
            default => abort(404),
        };
    }
}
