<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityAssignment;
use App\Models\Learner;
use App\Models\OpenRepositoryListing;
use App\Models\ReadingSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

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
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'learner_code' => ['required', 'string', 'max:20'],
            'pin' => ['required', 'digits:4'],
        ]);

        $code = strtoupper(trim($credentials['learner_code']));

        // Keyed by the raw submitted code (not a resolved Learner ID) so an
        // unknown code and a real code with a wrong PIN are throttled
        // identically — the whole point of the generic message below is
        // that neither the error nor the lockout behavior may reveal
        // whether the code itself exists.
        $throttleKey = 'learner-login:'.$code.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $minutes = (int) ceil(RateLimiter::availableIn($throttleKey) / 60);

            throw ValidationException::withMessages([
                'pin' => "Too many attempts. Try again in {$minutes} minute".($minutes === 1 ? '' : 's').'.',
            ]);
        }

        $learner = Learner::where('learner_code', $code)->first();

        if (! $learner || ! Hash::check($credentials['pin'], $learner->pin)) {
            RateLimiter::hit($throttleKey, 900);

            throw ValidationException::withMessages([
                'pin' => 'Incorrect PIN.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        Auth::guard('learner')->login($learner);
        $request->session()->regenerate();

        // PlacementDiagnostic_Addition.txt Part 2: checked right after PIN
        // success, before the normal Dashboard. The standing
        // 'learner.diagnostic' middleware guard (routes/web.php) covers
        // bookmarked/back-buttoned requests that skip this login moment —
        // this redirect just avoids an unnecessary extra hop for the
        // common case of a fresh login.
        $hasCompletedDiagnostic = ReadingSession::where('learner_id', $learner->id)
            ->where('session_type', 'Diagnostic')
            ->exists();

        if (! $hasCompletedDiagnostic) {
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
        return view('learner.dashboard', [
            'learner' => $request->user('learner'),
        ]);
    }

    /**
     * "Start Reading Activity" — Learner Actor Prompt Step 3: "Finding
     * what to read." Step 1: resolve the Teacher-assigned Activity using
     * priority — direct-to-this-Learner first, then Class, then Class's
     * Group tag — "first match wins" is read here as the first PRIORITY
     * LEVEL with any results (not a single row), since a Teacher can
     * assign more than one activity at the same level. Step 2: ALSO
     * gather any Activities unlocked via the Open Repository — always
     * included alongside the assignment result, not just a fallback when
     * there's no assignment. Step 3: exactly one option total goes
     * straight in; more than one shows the picker with a source label
     * per option ("Assigned by your Teacher" vs. "Extra Practice").
     *
     * SCOPE BOUNDARY: still stops short of the real read-aloud/AI-scoring
     * engine (Sprint 4 Slices 2-3) — an honest note, not a fake flow.
     */
    public function findActivity(Request $request): View
    {
        $learner = $request->user('learner');

        $assignmentIds = ActivityAssignment::where('learner_id', $learner->id)->pluck('activity_id');

        if ($assignmentIds->isEmpty() && $learner->class_id) {
            $assignmentIds = ActivityAssignment::where('class_id', $learner->class_id)->pluck('activity_id');
        }

        if ($assignmentIds->isEmpty() && $learner->schoolClass?->group_tag) {
            $assignmentIds = ActivityAssignment::where('group_tag', $learner->schoolClass->group_tag)
                ->where('assigned_by_teacher_id', $learner->schoolClass->teacher_id)
                ->pluck('activity_id');
        }

        $assignedActivities = Activity::whereIn('id', $assignmentIds)
            ->where('status', 'Approved')
            ->get();

        $unlockedListingActivityIds = OpenRepositoryListing::whereHas(
            'unlocks',
            fn ($query) => $query->where('learner_id', $learner->id)
        )->pluck('activity_id');

        $unlockedActivities = Activity::whereIn('id', $unlockedListingActivityIds)
            ->where('status', 'Approved')
            ->whereNotIn('id', $assignedActivities->pluck('id'))
            ->get();

        $options = $assignedActivities->map(fn ($activity) => ['activity' => $activity, 'source' => 'Assigned by your Teacher'])
            ->concat($unlockedActivities->map(fn ($activity) => ['activity' => $activity, 'source' => 'Extra Practice']));

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

        return view('learner.activity-found', ['activity' => $activity]);
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
