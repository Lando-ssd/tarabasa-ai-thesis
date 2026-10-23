<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ScoringJob;
use App\Services\BackgroundScoring;
use App\Services\LearnerReadingService;
use App\Services\ReadingAiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * My Bookshelf: the list of books a child has finished, and the real task-flow
 * screens for a free/unscored re-read. See LearnerReadingService::recordFreeReattempt()'s
 * own doc comment for why re-reading doesn't score.
 */
class BookshelfController extends Controller
{
    public function index(Request $request): View
    {
        $learner = $request->user('learner');

        return view('learner.bookshelf', ['learner' => $learner, 'books' => $learner->bookshelfBooks()]);
    }

    /**
     * The recording screen for a free re-read — re-verifies real reading
     * history server-side (Activity::hasCompletedPracticeReadingFor()),
     * never assumed safe just because the Bookshelf UI only links to
     * Activities it already listed.
     */
    public function reread(Request $request, Activity $activity): View
    {
        $learner = $request->user('learner');

        abort_unless($activity->hasCompletedPracticeReadingFor($learner), 403);

        return view('learner.bookshelf-reread', ['activity' => $activity, 'learner' => $learner]);
    }

    /** A free re-read from the shelf. Scored in the background like any reading (see LearnerReadingController::submitRecording). */
    public function submitReread(Request $request, Activity $activity, ReadingAiClient $readingAi, LearnerReadingService $service, BackgroundScoring $scoring): View|JsonResponse
    {
        $learner = $request->user('learner');

        abort_unless($activity->hasCompletedPracticeReadingFor($learner), 403);

        $validated = $request->validate([
            'audio' => ['required', 'file', 'max:15360'],
            'client_key' => ['nullable', 'string', 'max:64'],
        ]);

        if ($request->expectsJson()) {
            return $scoring->submit($request, ScoringJob::REREAD, $learner, $activity, $validated['audio']);
        }

        $outcome = $service->recordFreeReattempt($activity, $validated['audio'], $readingAi);

        return $this->renderReread($outcome, $activity);
    }

    public function renderReread(array $outcome, Activity $activity): View
    {
        if ($outcome['status'] === 'unclear') {
            return view('learner.bookshelf-reread-unclear', ['activity' => $activity]);
        }

        return view('learner.bookshelf-reread-results', [
            'activity' => $activity,
            'accuracy' => $outcome['accuracy'],
            'wcpm' => $outcome['wcpm'],
            'wordBreakdown' => $outcome['wordBreakdown'],
            'extraWordsSaid' => $outcome['extraWordsSaid'],
            'wordsToPractice' => $outcome['wordsToPractice'],
            'wordCounts' => $outcome['wordCounts'] ?? null,
        ]);
    }
}
