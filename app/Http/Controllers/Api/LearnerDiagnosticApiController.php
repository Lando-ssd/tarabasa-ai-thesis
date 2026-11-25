<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\SerializesLearner;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Services\LearnerDiagnosticService;
use App\Services\ReadingAiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile Learner API — the first-login diagnostic. Thin JSON wrappers
 * over LearnerDiagnosticService, the exact same logic
 * LearnerDiagnosticController (web) calls — see docs/HISTORY.md's "Mobile API
 * layer" entry. NOT gated by 'learner.diagnostic.api' (see routes/api.php)
 * — this is how a Learner completes it, so it can't require itself to
 * already be complete.
 */
class LearnerDiagnosticApiController extends Controller
{
    use SerializesLearner;

    public function passage(Request $request, LearnerDiagnosticService $service): JsonResponse
    {
        $learner = $request->user();

        // A child who has finished the first check must not start another one by asking for a passage again (the Android back
        // button, a stale screen): ensureBundleGenerated() would open a new check, which locks her out of every other screen
        // until she reads it and can place her again. A check that is still in progress is not "completed", so it carries on.
        if ($service->hasGenuinelyCompletedDiagnostic($learner)) {
            return response()->json(['error' => 'diagnostic_done', 'message' => 'Your reading check is already done.'], 409);
        }

        $state = $service->ensureBundleGenerated($learner);

        $activity = Activity::findOrFail($service->currentActivityId($state));
        $present = $service->presentation($activity);

        return response()->json([
            'activity' => [
                'id' => $activity->id,
                'title' => $activity->title,
                'passageText' => $activity->passage_text,
                // 'letters' (say each letter's name) or 'passage' (read the phonics text).
                'kind' => $present['kind'],
                'prompt' => $present['prompt'],
                'letters' => $present['letters'],
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
                'newBadges' => $this->newBadgesPayload($outcome['newBadges']),
            ]);
        }

        return response()->json($outcome);
    }
}
