<?php

namespace App\Http\Controllers;

use App\Models\Learner;
use App\Models\ReadingSession;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Services\TeacherInsights;
use App\Support\ChildSummary;
use App\Support\ErrorPatterns;
use App\Support\ReadingLevel;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
    public function teacherIndex(Request $request, TeacherInsights $insights): View
    {
        $teacher = $request->user()->teacher;

        $classIds = SchoolClass::where('teacher_id', $teacher->id)->pluck('id');

        $learners = Learner::whereIn('class_id', $classIds)
            ->with('schoolClass')
            ->orderBy('first_name')
            ->get();

        $groupTags = SchoolClass::where('teacher_id', $teacher->id)
            ->whereNotNull('group_tag')
            ->where('group_tag', '!=', '')
            ->distinct()
            ->orderBy('group_tag')
            ->pluck('group_tag');

        // It opens on the whole picture; one learner and one group are a tap away.
        $mode = in_array($request->query('mode'), ['learner', 'group'], true) ? $request->query('mode') : 'overview';

        $overview = null;
        $period = is_string($request->query('period')) && array_key_exists($request->query('period'), TeacherInsights::PERIODS) ? $request->query('period') : 'week';
        $classChoice = $request->query('class') === 'all' ? null : ((int) $request->query('class') ?: null);
        if ($mode === 'overview') {
            $classes = $insights->classesFor($teacher, $classChoice);
            $classChoice = $classes->count() === 1 && $classChoice !== null ? $classes->first()->id : null;
            $overview = $insights->overview($teacher, $classes, $period);
        }

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
            // The same day streak and weekly goal the child sees on their own Home.
            'summary' => $selectedLearner ? ChildSummary::for($selectedLearner) : null,
            'pickerClasses' => SchoolClass::whereIn('id', $classIds)->orderByDesc('school_year')->orderBy('name')->get(),
            'overview' => $overview,
            'period' => $period,
            'classChoice' => $classChoice,
            'periods' => TeacherInsights::PERIODS,
        ]);
    }

    /**
     * The class report as a CSV, one row per learner in the chosen classes and period. Nothing a
     * report does not need (no learner code, no contact details), and any cell a spreadsheet could
     * run as a formula is neutralised.
     */
    public function teacherReport(Request $request, TeacherInsights $insights): StreamedResponse
    {
        $teacher = $request->user()->teacher;
        $period = is_string($request->query('period')) && array_key_exists($request->query('period'), TeacherInsights::PERIODS) ? $request->query('period') : 'week';
        $classId = $request->query('class') === 'all' ? null : ((int) $request->query('class') ?: null);

        $classes = $insights->classesFor($teacher, $classId);
        $rows = $insights->reportRows($teacher, $classes, $period);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // so Excel reads the names as UTF-8
            fputcsv($out, TeacherInsights::reportHeader());
            foreach ($rows as $row) {
                fputcsv($out, array_map([TeacherInsights::class, 'csvSafe'], $row));
            }
            fclose($out);
        }, 'class-report-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
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
            'summary' => $selectedLearner ? ChildSummary::for($selectedLearner) : null,
            'level' => $selectedLearner ? $this->parentLevel($selectedLearner) : null,
            'home' => $selectedLearner ? $this->homePractice($selectedLearner) : null,
        ]);
    }

    /**
     * The child's reading level in the school's own words (Phil-IRI, see ReadingLevel) with a plain
     * sentence for a parent. The Phil-IRI names can sound harsh to a parent ("frustration level"), so
     * each comes with what it means in everyday words.
     */
    private function parentLevel(Learner $learner): array
    {
        $level = ReadingLevel::forAdult($learner);
        $name = $learner->first_name;

        $level['plain'] = [
            'non' => "{$name} is just starting with letters and sounds. Short, happy practice every day helps most.",
            'frustration' => "Reading is still hard for {$name} right now. That is common while learning, and short daily practice helps.",
            'instructional' => "{$name} reads well with some help and is learning steadily.",
            'independent' => "{$name} reads comfortably alone.",
            'unchecked' => "{$name} has not finished the first reading check yet.",
        ][$level['band']] ?? '';

        return $level;
    }

    /**
     * What to practise at home, taken from the kind of mistake the child keeps making across their
     * recent readings (ErrorPatterns). Only said when there is enough evidence, in a parent's words,
     * with one thing to try and the curriculum competency it practises. Null when there is no clear
     * pattern: a few mistakes are not a pattern, and nothing is invented.
     */
    private function homePractice(Learner $learner): ?array
    {
        $profile = ErrorPatterns::forLearner($learner);
        $main = ErrorPatterns::mainPattern($profile);

        if ($main === null) {
            return null;
        }

        $category = ErrorPatterns::CATEGORIES[$main];

        return [
            'say' => $category['parent'],
            'tip' => $category['tip'],
            'code' => $category['code'],
            'codeText' => $category['codeText'],
            'early' => $profile['early'],
            'examples' => $profile['top'][0]['examples'] ?? [],
            'words' => array_slice(array_keys($profile['missedWords']), 0, 6),
        ];
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
            // What kind of mistakes this child makes (needs a few readings before it says anything).
            'patterns' => ErrorPatterns::profile($sessions->sortByDesc('timestamp')->values()),
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

        $sourceSummary = collect(['Teacher', 'Parent'])->mapWithKeys(function (string $source) use ($sessions) {
            $rows = $sessions->where('initiated_by', $source);
            $avg = $rows->avg('accuracy_percent');

            return [$source => ['count' => $rows->count(), 'avg_accuracy' => $avg === null ? null : (int) round($avg)]];
        });

        // Who needs help first: lowest level first, then lowest average score.
        $rank = ['Beginning' => 0, 'Developing' => 1, 'Proficient' => 2];

        $learnerRows = $learners->map(function (Learner $learner) use ($sessions) {
            $own = $sessions->where('learner_id', $learner->id);
            $avg = $own->avg('accuracy_percent');

            return [
                'learner' => $learner,
                'session_count' => $own->count(),
                'avg_accuracy' => $avg === null ? null : (int) round($avg),
            ];
        })->sortBy(fn (array $row) => [$rank[$row['learner']->mastery_level] ?? 0, $row['avg_accuracy'] ?? 0])->values();

        return [
            'source_summary' => $sourceSummary,
            'learner_rows' => $learnerRows,
        ];
    }
}
