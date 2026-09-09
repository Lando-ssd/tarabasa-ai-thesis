<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Services\LearnerDiagnosticService;
use App\Services\ReadingAiClient;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Sprint 4 Slice 4 — the first-login diagnostic
 * (TaraBasaAI_PlacementDiagnostic_Addition.txt +
 * TaraBasaAI_AdaptiveDiagnostic_Correction.txt). PROVISIONAL scope cuts
 * recorded prominently in CLAUDE.md: the staircase adapts WITHIN the
 * Learner's own grade only, and every Learner starts at Medium regardless
 * of their Parent placement-quiz pattern.
 *
 * A thin Blade-rendering wrapper — the actual staircase logic (bundle
 * generation, scoring, mastery-level finalization, Adaptive_Recommendator
 * initialize()) lives in LearnerDiagnosticService, shared verbatim with
 * the mobile API's Api\LearnerDiagnosticApiController. See CLAUDE.md's
 * "Mobile API layer" entry for why this was extracted.
 */
class LearnerDiagnosticController extends Controller
{
    /**
     * Intro screen — Part 4.1: never use the word "test," frame this as
     * "Let's Read Together."
     */
    public function show(Request $request, LearnerDiagnosticService $service): View
    {
        $learner = $request->user('learner');

        $service->ensureBundleGenerated($learner);

        return view('learner.diagnostic-intro', ['learner' => $learner]);
    }

    /**
     * The current passage in the staircase, whichever tier/variant that
     * currently is per the tracked state.
     */
    public function passage(Request $request, LearnerDiagnosticService $service): View
    {
        $learner = $request->user('learner');
        $state = $service->ensureBundleGenerated($learner);

        $activity = Activity::findOrFail($service->currentActivityId($state));

        return view('learner.diagnostic-passage', [
            'activity' => $activity,
            'learner' => $learner,
            'passageNumber' => $state['passages_done'] + 1,
            'maxPassages' => 3,
        ]);
    }

    public function submitRecording(Request $request, ReadingAiClient $readingAi, LearnerDiagnosticService $service): View
    {
        $learner = $request->user('learner');
        $state = $service->diagnosticState($learner);

        abort_if($state === null, 403, 'No diagnostic in progress.');

        $activity = Activity::findOrFail($service->currentActivityId($state));

        $validated = $request->validate([
            'audio' => ['required', 'file', 'max:15360'],
        ]);

        $outcome = $service->recordAttempt($learner, $state, $activity, $validated['audio'], $readingAi);

        return match ($outcome['status']) {
            'unclear' => view('learner.reading-unclear', ['activity' => null, 'final' => $outcome['final'], 'isDiagnostic' => true]),
            'continue' => view('learner.diagnostic-encourage', [
                'message' => $outcome['message'],
                'passagesDone' => $outcome['passagesDone'],
                'maxPassages' => $outcome['maxPassages'],
            ]),
            'finished' => view('learner.diagnostic-results', [
                'learner' => $outcome['learner'],
                'finalLevel' => $outcome['finalLevel'],
                'resultLabel' => $outcome['resultLabel'],
            ]),
        };
    }
}
