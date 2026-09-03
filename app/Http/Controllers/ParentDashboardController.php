<?php

namespace App\Http\Controllers;

use App\Models\Learner;
use App\Models\Notification;
use App\Models\ReadingSession;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ParentDashboardController extends Controller
{
    /**
     * The real Parent Dashboard — replaces the generic
     * dashboard-placeholder.blade.php for this role. Built from
     * docs/design-reference-html/tarabasa-parent-dashboard-v2.html as the
     * real foundation. Two things in that prototype don't exist yet as
     * real features and are deliberately NOT wired to fake actions here:
     * "Start Practice" (a Parent-initiated ad-hoc reading session — the
     * Repository-unlock flow this depends on isn't built, see
     * LearnerReadingController's own note that initiated_by is still
     * always 'Teacher') and "Browse Repository" — both marked "Coming
     * Soon" instead of a dead/fake link. Badges also aren't a real
     * system yet (Sprint 4 decision), so the prototype's Badges stat is
     * replaced with the real Points figure instead of showing a
     * plausible-looking fake count.
     */
    public function index(Request $request): View
    {
        $parent = $request->user()->parentProfile;

        $learners = $parent->learners()->with('schoolClass')->orderBy('first_name')->get();

        $flaggedByLearner = ReadingSession::whereIn('learner_id', $learners->pluck('id'))
            ->where('session_type', '!=', 'Diagnostic')
            ->orderByDesc('timestamp')
            ->get()
            ->groupBy('learner_id')
            ->map(fn ($sessions) => (bool) $sessions->first()->flagged_needs_attention);

        $selectedLearner = null;
        $childData = null;

        if ($learners->isNotEmpty()) {
            $requestedId = (int) $request->query('learner_id', $learners->first()->id);
            $selectedLearner = $learners->firstWhere('id', $requestedId) ?? $learners->first();
            $childData = $this->childData($selectedLearner);
        }

        return view('parent.dashboard', [
            'user' => $request->user(),
            'learners' => $learners,
            'flaggedByLearner' => $flaggedByLearner,
            'selectedLearner' => $selectedLearner,
            'childData' => $childData,
            'unreadNotifications' => Notification::where('recipient_user_id', $request->user()->id)
                ->where('is_read', false)
                ->count(),
        ]);
    }

    private function childData(Learner $learner): array
    {
        $recentSessions = ReadingSession::where('learner_id', $learner->id)
            ->where('session_type', '!=', 'Diagnostic')
            ->with('activity')
            ->orderByDesc('timestamp')
            ->take(3)
            ->get();

        $mostRecent = $recentSessions->first();

        return [
            'source_summary' => ReadingSession::sourceSummaryForLearner($learner->id),
            'recent_sessions' => $recentSessions,
            'is_flagged' => (bool) ($mostRecent->flagged_needs_attention ?? false),
            'wcpm' => $mostRecent->wcpm ?? null,
        ];
    }
}
