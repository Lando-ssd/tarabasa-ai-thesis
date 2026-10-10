<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\SerializesLearner;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Services\LearnerReadingService;
use App\Services\ReadingAiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile Learner API — real Practice reading submission. Thin JSON
 * wrapper over LearnerReadingService, the exact same logic
 * LearnerReadingController (web) calls — see CLAUDE.md's "Mobile API
 * layer" entry. For a comprehension activity the app sends the picked
 * choice text per question in 'answers'; the score is worked out here on
 * the server against the stored answers (the activity detail endpoint
 * only ever sends the questions and their choices).
 */
class LearnerReadingApiController extends Controller
{
    use SerializesLearner;

    public function submitRecording(Request $request, Activity $activity, ReadingAiClient $readingAi, LearnerReadingService $service): JsonResponse
    {
        $learner = $request->user();

        abort_unless($activity->isAccessibleByLearner($learner), 403);

        $validated = $request->validate([
            'audio' => ['required', 'file', 'max:15360'],
            'answers' => ['nullable', 'array'],
        ]);

        $outcome = $service->recordAttempt($learner, $activity, $validated['audio'], $validated['answers'] ?? null, $readingAi);

        if ($outcome['status'] === 'unclear') {
            return response()->json(['status' => 'unclear', 'final' => $outcome['final']]);
        }

        return response()->json([
            'status' => 'scored',
            'activityId' => $outcome['activity']->id,
            'learner' => $this->learnerPayload($outcome['learner']),
            'accuracy' => $outcome['accuracy'],
            'wcpm' => $outcome['wcpm'],
            'levelBefore' => $outcome['levelBefore'],
            'levelAfter' => $outcome['levelAfter'],
            'levelChanged' => $outcome['levelChanged'],
            'progress' => $outcome['progress'],
            'levelWentUp' => $outcome['levelWentUp'],
            'pointsEarned' => $outcome['pointsEarned'],
            'wordBreakdown' => $outcome['wordBreakdown'],
            'extraWordsSaid' => $outcome['extraWordsSaid'],
            'wordsToPractice' => $outcome['wordsToPractice'],
            'wordCounts' => $outcome['wordCounts'] ?? null,
            'comprehension' => $outcome['comprehension'],
            'newBadges' => $outcome['newBadges'],
        ]);
    }

    /**
     * A practice try before the real reading (the website's "Your turn" stage). Free and unscored: the child
     * gets the same word by word feedback, nothing is saved, and no points, streak or level move. Two tries
     * at most; a try that could not be heard does not use one up.
     */
    public function submitPractice(Request $request, Activity $activity, ReadingAiClient $readingAi, LearnerReadingService $service): JsonResponse
    {
        $learner = $request->user();

        abort_unless($activity->isAccessibleByLearner($learner), 403);

        if ($service->practiceTriesLeft($learner, $activity) === 0) {
            return response()->json(['status' => 'no_tries_left', 'triesLeft' => 0]);
        }

        $validated = $request->validate([
            'audio' => ['required', 'file', 'max:15360'],
        ]);

        $outcome = $service->recordPractice($learner, $activity, $validated['audio'], $readingAi);

        if ($outcome['status'] === 'unclear') {
            return response()->json(['status' => 'unclear', 'triesLeft' => $outcome['triesLeft']]);
        }

        return response()->json([
            'status' => 'scored',
            'activityId' => $activity->id,
            'accuracy' => $outcome['accuracy'],
            'wordBreakdown' => $outcome['wordBreakdown'],
            'extraWordsSaid' => $outcome['extraWordsSaid'],
            'wordsToPractice' => $outcome['wordsToPractice'],
            'wordCounts' => $outcome['wordCounts'],
            'triesLeft' => $outcome['triesLeft'],
        ]);
    }
}
