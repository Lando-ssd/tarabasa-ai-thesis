<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Services\LearnerReadingService;
use App\Services\ReadingAiClient;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * My Bookshelf's list view now renders inline on the Dashboard (see
 * LearnerAuthController::dashboard() + Learner::bookshelfBooks()) — this
 * controller is left with only the real task-flow screens: a free/
 * unscored re-read. See LearnerReadingService::recordFreeReattempt()'s
 * own doc comment for why re-reading doesn't score.
 */
class BookshelfController extends Controller
{
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

    public function submitReread(Request $request, Activity $activity, ReadingAiClient $readingAi, LearnerReadingService $service): View
    {
        $learner = $request->user('learner');

        abort_unless($activity->hasCompletedPracticeReadingFor($learner), 403);

        $validated = $request->validate([
            'audio' => ['required', 'file', 'max:15360'],
        ]);

        $outcome = $service->recordFreeReattempt($activity, $validated['audio'], $readingAi);

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
        ]);
    }
}
