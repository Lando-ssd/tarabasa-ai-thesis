<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Services\LearnerReadingService;
use App\Services\ReadingAiClient;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Sprint 4 Slice 3 — real ReadingSession persistence, mastery-level
 * adjustment, points/streak, PersonalWordBank, and the results screen.
 * Calls the real, deployed Reading-api — no mock.
 *
 * A thin Blade-rendering wrapper — the actual scoring/mastery/adaptive
 * logic lives in LearnerReadingService, shared verbatim with the mobile
 * API's Api\LearnerReadingApiController. See CLAUDE.md's "Mobile API
 * layer" entry for why this was extracted.
 */
class LearnerReadingController extends Controller
{
    public function submitRecording(Request $request, Activity $activity, ReadingAiClient $readingAi, LearnerReadingService $service): View
    {
        $learner = $request->user('learner');

        abort_unless($activity->isAccessibleByLearner($learner), 403);

        // Reading-api streams and hard-rejects over 15MB itself (413), but
        // rejecting an oversized file here first is a cheap, fast pre-check
        // that saves the Learner's bandwidth/time on an upload we already
        // know will fail. 15360 KB = 15MB, matching Reading-api's own
        // MAX_UPLOAD_MB exactly (confirmed by reading its real source).
        $validated = $request->validate([
            'audio' => ['required', 'file', 'max:15360'],
            'answers' => ['nullable', 'array'],
        ]);

        $outcome = $service->recordAttempt($learner, $activity, $validated['audio'], $validated['answers'] ?? null, $readingAi);

        if ($outcome['status'] === 'unclear') {
            return view('learner.reading-unclear', ['activity' => $activity, 'final' => $outcome['final']]);
        }

        return view('learner.reading-results', [
            'activity' => $outcome['activity'],
            'learner' => $outcome['learner'],
            'accuracy' => $outcome['accuracy'],
            'wcpm' => $outcome['wcpm'],
            'levelBefore' => $outcome['levelBefore'],
            'levelAfter' => $outcome['levelAfter'],
            'levelChanged' => $outcome['levelChanged'],
            'levelWentUp' => $outcome['levelWentUp'],
            'pointsEarned' => $outcome['pointsEarned'],
            'wordBreakdown' => $outcome['wordBreakdown'],
            'extraWordsSaid' => $outcome['extraWordsSaid'],
            'wordsToPractice' => $outcome['wordsToPractice'],
            'wordCounts' => $outcome['wordCounts'] ?? null,
            'comprehension' => $outcome['comprehension'],
            'newBadges' => $outcome['newBadges'],
            'progress' => $outcome['progress'] ?? null,
        ]);
    }

    /**
     * A practice try before the real reading (stage 2 of the practice stage). Free and unscored:
     * the child sees the same word by word look, but nothing is saved and no points, streak or level
     * move. Two tries at most; after that the child reads for real.
     */
    public function submitPractice(Request $request, Activity $activity, ReadingAiClient $readingAi, LearnerReadingService $service): View|\Illuminate\Http\RedirectResponse
    {
        $learner = $request->user('learner');

        abort_unless($activity->isAccessibleByLearner($learner), 403);

        if ($service->practiceTriesLeft($learner, $activity) === 0) {
            return redirect()->route('learner.activity.show', [$activity, 'stage' => 'real']);
        }

        $validated = $request->validate([
            'audio' => ['required', 'file', 'max:15360'],
        ]);

        $outcome = $service->recordPractice($learner, $activity, $validated['audio'], $readingAi);

        if ($outcome['status'] === 'unclear') {
            return view('learner.activity-practice-unclear', ['activity' => $activity]);
        }

        return view('learner.activity-practice-result', [
            'activity' => $activity,
            'learner' => $learner,
            'wordBreakdown' => $outcome['wordBreakdown'],
            'extraWordsSaid' => $outcome['extraWordsSaid'],
            'wordCounts' => $outcome['wordCounts'],
            'accuracy' => $outcome['accuracy'],
            'triesLeft' => $outcome['triesLeft'],
        ]);
    }

    /**
     * Called quietly by a screen where a child is about to read or speak, so the scoring service is
     * awake by the time the recording is sent. Always answers at once with nothing to show.
     */
    public function warm(ReadingAiClient $readingAi): \Illuminate\Http\Response
    {
        $readingAi->wake();

        // The adaptive recommender sleeps the same way and is asked right after a reading is scored.
        if (config('services.adaptive_recommender.url') && ! \App\Support\ServiceWake::isKnownAwake('recommender')
            && \Illuminate\Support\Facades\Cache::add('recommender-wake-requested', true, now()->addMinutes(2))) {
            \App\Jobs\WakeServiceJob::start('recommender');
        }

        return response()->noContent();
    }
}
