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
 * layer" entry. The comprehension quiz's 'answers' field is accepted
 * here for forward-compatibility but is a flagged fast-follow — no MVP
 * mobile screen sends it yet, since the activity-detail endpoint doesn't
 * expose follow_up_questions to build that screen from.
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
            'levelWentUp' => $outcome['levelWentUp'],
            'pointsEarned' => $outcome['pointsEarned'],
            'wordBreakdown' => $outcome['wordBreakdown'],
            'extraWordsSaid' => $outcome['extraWordsSaid'],
            'wordsToPractice' => $outcome['wordsToPractice'],
            'comprehension' => $outcome['comprehension'],
        ]);
    }
}
