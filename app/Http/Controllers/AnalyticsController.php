<?php

namespace App\Http\Controllers;

use App\Models\Learner;
use App\Models\ReadingSession;
use App\Models\SchoolClass;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    /**
     * Teacher Actor Prompt Step 10 — By Learner / By Group, Teacher-Assigned
     * vs. Parent-Initiated always shown as two separate numbers, never
     * combined. No 'teacher.active' guard: this is read-only, same
     * visibility rule as Class Management's index (approval gates
     * "touching real students," not viewing).
     *
     * Diagnostic-type sessions are excluded everywhere here — a one-time
     * placement test isn't "reading practice" and its score isn't
     * comparable to an ongoing accuracy trend (confirmed with the user
     * before building this).
     */
    public function teacherIndex(Request $request): View
    {
        $teacher = $request->user()->teacher;

        $classIds = SchoolClass::where('teacher_id', $teacher->id)->pluck('id');

        $learners = Learner::whereIn('class_id', $classIds)
            ->orderBy('first_name')
            ->get();

        $groupTags = SchoolClass::where('teacher_id', $teacher->id)
            ->whereNotNull('group_tag')
            ->where('group_tag', '!=', '')
            ->distinct()
            ->orderBy('group_tag')
            ->pluck('group_tag');

        $mode = $request->query('mode') === 'group' ? 'group' : 'learner';

        $selectedLearner = null;
        $learnerStats = null;
        $selectedGroupTag = null;
        $groupStats = null;

        if ($mode === 'learner' && $learners->isNotEmpty()) {
            $requestedId = (int) $request->query('learner_id', $learners->first()->id);
            $selectedLearner = $learners->firstWhere('id', $requestedId) ?? $learners->first();
            $learnerStats = $this->learnerStats($selectedLearner);
        }

        if ($mode === 'group' && $groupTags->isNotEmpty()) {
            $requestedTag = $request->query('group_tag', $groupTags->first());
            $selectedGroupTag = $groupTags->contains($requestedTag) ? $requestedTag : $groupTags->first();
            $groupStats = $this->groupStats($teacher, $selectedGroupTag);
        }

        return view('teacher.analytics', [
            'teacher' => $teacher,
            'mode' => $mode,
            'learners' => $learners,
            'groupTags' => $groupTags,
            'selectedLearner' => $selectedLearner,
            'learnerStats' => $learnerStats,
            'selectedGroupTag' => $selectedGroupTag,
            'groupStats' => $groupStats,
        ]);
    }

    /**
     * Parent Actor Prompt Step 7 — Progress. Per-child, same
     * Teacher-Assigned/Parent-Initiated split and accuracy trend as the
     * Teacher's By-Learner view, since it's the same underlying data from
     * this child's own perspective.
     */
    public function parentIndex(Request $request): View
    {
        $parent = $request->user()->parentProfile;

        $learners = $parent->learners()->orderBy('first_name')->get();

        $selectedLearner = null;
        $learnerStats = null;

        if ($learners->isNotEmpty()) {
            $requestedId = (int) $request->query('learner_id', $learners->first()->id);
            $selectedLearner = $learners->firstWhere('id', $requestedId) ?? $learners->first();
            $learnerStats = $this->learnerStats($selectedLearner);
        }

        return view('parent.progress', [
            'parent' => $parent,
            'learners' => $learners,
            'selectedLearner' => $selectedLearner,
            'learnerStats' => $learnerStats,
        ]);
    }

    /**
     * Shared by both actors: the same Learner, the same underlying
     * ReadingSession rows, viewed from either side.
     */
    private function learnerStats(Learner $learner): array
    {
        $sessions = ReadingSession::where('learner_id', $learner->id)
            ->where('session_type', '!=', 'Diagnostic')
            ->with('activity')
            ->orderBy('timestamp')
            ->get();

        $sourceSummary = ReadingSession::sourceSummaryForLearner($learner->id);

        $ordered = $sessions->values();
        $chartWidth = 300;
        $chartLeft = 60;
        $chartTop = 10;
        $chartBottom = 140;
        $count = $ordered->count();

        $chartPoints = $ordered->map(function (ReadingSession $session, int $i) use ($ordered, $count, $chartWidth, $chartLeft, $chartTop, $chartBottom) {
            $x = $count > 1
                ? $chartLeft + ($i / ($count - 1)) * $chartWidth
                : $chartLeft;
            $accuracy = max(0, min(100, $session->accuracy_percent ?? 0));
            $y = $chartBottom - ($accuracy / 100) * ($chartBottom - $chartTop);

            return [
                'x' => round($x, 1),
                'y' => round($y, 1),
                'label' => '#' . ($i + 1),
                'accuracy' => round($accuracy),
            ];
        });

        return [
            'source_summary' => $sourceSummary,
            'chart_points' => $chartPoints,
            'polyline' => $chartPoints->map(fn (array $p) => "{$p['x']},{$p['y']}")->implode(' '),
            'history' => $sessions->sortByDesc('timestamp')->values(),
        ];
    }

    private function groupStats(Teacher $teacher, string $groupTag): array
    {
        $classIds = SchoolClass::where('teacher_id', $teacher->id)
            ->where('group_tag', $groupTag)
            ->pluck('id');

        $learners = Learner::whereIn('class_id', $classIds)
            ->orderBy('first_name')
            ->get();

        $sessions = ReadingSession::whereIn('learner_id', $learners->pluck('id'))
            ->where('session_type', '!=', 'Diagnostic')
            ->get();

        $sourceSummary = collect(['Teacher', 'Parent'])->mapWithKeys(fn (string $source) => [
            $source => ['count' => $sessions->where('initiated_by', $source)->count()],
        ]);

        $learnerRows = $learners->map(fn (Learner $learner) => [
            'learner' => $learner,
            'session_count' => $sessions->where('learner_id', $learner->id)->count(),
        ]);

        return [
            'source_summary' => $sourceSummary,
            'learner_rows' => $learnerRows,
        ];
    }
}
