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
            'comprehension' => $outcome['comprehension'],
        ]);
    }
}
