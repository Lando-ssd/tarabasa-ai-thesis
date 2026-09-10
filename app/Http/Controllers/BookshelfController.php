<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ReadingSession;
use App\Services\LearnerReadingService;
use App\Services\ReadingAiClient;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "My Bookshelf" — Activities this Learner has genuinely completed a
 * real Practice reading of before, real data reuse (a query over
 * already-existing ReadingSession rows, no new scoring logic for the
 * listing itself). Re-reading is deliberately free/unscored — see
 * LearnerReadingService::recordFreeReattempt()'s own doc comment for
 * the reasoning.
 */
class BookshelfController extends Controller
{
    public function index(Request $request): View
    {
        $learner = $request->user('learner');

        $books = ReadingSession::where('learner_id', $learner->id)
            ->where('session_type', 'Practice')
            ->with('activity')
            ->get()
            ->filter(fn (ReadingSession $session) => $session->activity !== null)
            ->groupBy('activity_id')
            ->map(function ($sessions) {
                return [
                    'activity' => $sessions->first()->activity,
                    'timesRead' => $sessions->count(),
                    'mostRecentDate' => $sessions->max('timestamp'),
                    'bestAccuracy' => $sessions->max('accuracy_percent'),
                ];
            })
            ->sortByDesc('mostRecentDate')
            ->values();

        return view('learner.bookshelf', ['learner' => $learner, 'books' => $books]);
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
