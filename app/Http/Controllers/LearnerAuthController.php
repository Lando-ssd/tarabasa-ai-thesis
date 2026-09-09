<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\LearnerBadge;
use App\Services\LearnerAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * A thin Blade-rendering wrapper — the actual credential-check/throttle
 * logic and the "what should I read?" picker resolution both live in
 * LearnerAuthService, shared verbatim with the mobile API's
 * Api\LearnerApiController. See CLAUDE.md's "Mobile API layer" entry for
 * why this was extracted.
 */
class LearnerAuthController extends Controller
{
    /**
     * Access — Learner Actor Prompt Step 1. Only the code-entry method is
     * built here (typing the learnerCode directly, standing in for a QR
     * scan). The avatar-tap variant (shown when a Parent is already logged
     * in on the device) is a separate enhancement to this same screen,
     * deliberately out of scope for this slice.
     */
    public function showLogin(): View
    {
        return view('learner.login');
    }

    /**
     * No password, no email — just learnerCode + a 4-digit PIN. A 4-digit
     * PIN has only 10,000 combinations, so this is throttled far more
     * aggressively than the adult login: 5 wrong attempts locks out for
     * 15 minutes (vs. 60 seconds for Teacher/Parent/Admin).
     */
    public function login(Request $request, LearnerAuthService $service): RedirectResponse
    {
        $credentials = $request->validate([
            'learner_code' => ['required', 'string', 'max:20'],
            'pin' => ['required', 'digits:4'],
        ]);

        $learner = $service->authenticate($credentials['learner_code'], $credentials['pin'], $request->ip());

        Auth::guard('learner')->login($learner);
        $request->session()->regenerate();

        // PlacementDiagnostic_Addition.txt Part 2: checked right after PIN
        // success, before the normal Dashboard. The standing
        // 'learner.diagnostic' middleware guard (routes/web.php) covers
        // bookmarked/back-buttoned requests that skip this login moment —
        // this redirect just avoids an unnecessary extra hop for the
        // common case of a fresh login.
        if (! $service->hasCompletedDiagnostic($learner)) {
            return redirect()->route('learner.diagnostic.show');
        }

        return redirect()->route('learner.dashboard');
    }

    /**
     * Learner Dashboard — Learner Actor Prompt Step 2. Real data straight
     * from the Learner's own record. "New" stands in for a null
     * mastery_level: never happens via the current wizard (which always
     * runs the placement quiz), but the column is nullable and the actor
     * prompt's edge cases explicitly call this state out.
     */
    public function dashboard(Request $request): View
    {
        $learner = $request->user('learner');
        $badges = LearnerBadge::summaryFor($learner);

        return view('learner.dashboard', [
            'learner' => $learner,
            'earnedBadgeCount' => count(array_filter($badges, fn (array $b) => $b['earned'])),
            'totalBadgeCount' => count($badges),
        ]);
    }

    /**
     * "Start Reading Activity" — Learner Actor Prompt Step 3: "Finding
     * what to read." Exactly one option total goes straight in; more than
     * one shows the picker with a source label per option.
     *
     * SCOPE BOUNDARY: still stops short of the real read-aloud/AI-scoring
     * engine (Sprint 4 Slices 2-3) — an honest note, not a fake flow.
     */
    public function findActivity(Request $request, LearnerAuthService $service): View
    {
        $options = $service->findActivityOptions($request->user('learner'));

        if ($options->count() === 1) {
            return view('learner.activity-found', ['activity' => $options->first()['activity']]);
        }

        return view('learner.activity-picker', ['options' => $options]);
    }

    /**
     * Opening one specific option from the picker. Re-verifies the
     * Learner actually has real access to this Activity server-side
     * (Validation & Edge Cases: "rejected outright by the backend — never
     * assumed safe just because the frontend only shows valid options"),
     * not just that they clicked a link that happened to show it.
     */
    public function showActivity(Request $request, Activity $activity): View
    {
        $learner = $request->user('learner');

        abort_unless($activity->isAccessibleByLearner($learner), 403);

        return view('learner.activity-found', ['activity' => $activity, 'learner' => $learner]);
    }

    /**
     * The +/- text-size control's own save call — a real preference, not
     * a scoring/assessment concern, so a lightweight JSON endpoint is
     * used here instead of this app's usual plain-form-submit convention:
     * a full page reload on every tap of a size button would fight the
     * whole point of the control feeling instant. The client applies the
     * new size immediately regardless of whether this call succeeds; a
     * failed save just means the choice doesn't persist past this visit,
     * never a blocked interaction.
     */
    public function updateReadingFontStep(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'step' => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        $request->user('learner')->update(['reading_font_step' => $validated['step']]);

        return response()->json(['ok' => true]);
    }

    /**
     * "Not you? Switch learner" — Learner Actor Prompt Step 8: destroys
     * the Learner session specifically, not the whole browser session. A
     * Parent could be simultaneously logged in on the same device (their
     * own 'web' guard session entry) — logging out only the 'learner'
     * guard leaves that fully intact. Deliberately does NOT call
     * session()->invalidate() the way the adult logout does, since that
     * would wipe every guard's session data, including the Parent's.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('learner')->logout();
        $request->session()->regenerate();

        return redirect()->route('learner.login');
    }
}
