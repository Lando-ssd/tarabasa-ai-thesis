<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Services\LearnerDiagnosticService;
use App\Services\ReadingAiClient;
use App\Support\DiagnosticPlacement;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Sprint 4 Slice 4 — the first-login diagnostic
 * (TaraBasaAI_PlacementDiagnostic_Addition.txt +
 * TaraBasaAI_AdaptiveDiagnostic_Correction.txt). PROVISIONAL scope cut
 * recorded prominently in CLAUDE.md: the staircase adapts WITHIN the
 * Learner's own grade only. It starts where the Parent said the child is
 * (config/diagnostic.php): Grade 1 pre-readers are asked for letters only,
 * and every reading item is phonics.
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
     *
     * This no longer writes the reading passages itself. That is a real
     * Gemini call of 55 to 105 seconds, and doing it here meant a new
     * learner stared at a blank page for a minute before the welcome could
     * even appear. passage() (the "I'm Ready" button) already calls
     * ensureBundleGenerated(), so the wait now happens there, on the intro
     * screen with a loading state. What gets generated is unchanged.
     */
    public function show(Request $request): View
    {
        $learner = $request->user('learner');

        return view('learner.diagnostic-intro', [
            'learner' => $learner,
            // A child the Parent described as just starting is asked for
            // letters, not reading, so the welcome should not promise reading.
            'startsWithLetters' => DiagnosticPlacement::startingRung($learner) === DiagnosticPlacement::LETTERS,
        ]);
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
            // Letters row or phonics passage, with the wording that fits it.
            'present' => $service->presentation($activity),
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
                'newBadges' => $outcome['newBadges'],
            ]),
        };
    }
}
