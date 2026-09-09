<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Services\LearnerDiagnosticService;
use App\Services\ReadingAiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile Learner API — the first-login diagnostic. Thin JSON wrappers
 * over LearnerDiagnosticService, the exact same logic
 * LearnerDiagnosticController (web) calls — see CLAUDE.md's "Mobile API
 * layer" entry. NOT gated by 'learner.diagnostic.api' (see routes/api.php)
 * — this is how a Learner completes it, so it can't require itself to
 * already be complete.
 */
class LearnerDiagnosticApiController extends Controller
{
    public function passage(Request $request, LearnerDiagnosticService $service): JsonResponse
    {
        $learner = $request->user();
        $state = $service->ensureBundleGenerated($learner);

        $activity = Activity::findOrFail($service->currentActivityId($state));

        return response()->json([
            'activity' => [
                'id' => $activity->id,
                'title' => $activity->title,
                'passageText' => $activity->passage_text,
            ],
            'passageNumber' => $state['passages_done'] + 1,
            'maxPassages' => 3,
        ]);
    }

    public function submitRecording(Request $request, ReadingAiClient $readingAi, LearnerDiagnosticService $service): JsonResponse
    {
        $learner = $request->user();
        $state = $service->diagnosticState($learner);

        abort_if($state === null, 403, 'No diagnostic in progress.');

        $activity = Activity::findOrFail($service->currentActivityId($state));

        $validated = $request->validate([
            'audio' => ['required', 'file', 'max:15360'],
        ]);

        $outcome = $service->recordAttempt($learner, $state, $activity, $validated['audio'], $readingAi);

        if ($outcome['status'] === 'finished') {
            return response()->json([
                'status' => 'finished',
                'finalLevel' => $outcome['finalLevel'],
                'resultLabel' => $outcome['resultLabel'],
                'newBadges' => $outcome['newBadges'],
            ]);
        }

        return response()->json($outcome);
    }
}
