<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Learner;
use App\Models\PromotionRecord;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Services\TeacherAlerts;
use App\Services\TeacherInsights;
use App\Services\TeacherPromotionService;
use App\Support\LearnerClock;
use App\Support\ReadingLevel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The phone app's Alerts, Analytics and Promotions for a Teacher (the last two under More). Thin JSON wrappers over the same classes the
 * website's pages use (TeacherAlerts, TeacherInsights, TeacherPromotionService), so the numbers, the words and the rules are the same.
 *
 * Looking is open to a Pending Teacher; marking an alert handled is too (it only hides a card), but giving an activity out and releasing or
 * claiming a learner need an Active one ('teacher.active' on those routes).
 */
class TeacherMoreApiController extends Controller
{
    public function __construct(
        private readonly TeacherAlerts $alerts,
        private readonly TeacherPromotionService $promotions,
    ) {}

    // ------------------------------------------------------------------ alerts

    /**
     * The open alerts, support first: who needs support, who is just starting to read, who moved up or is ready to, who has gone quiet. Each
     * has the evidence, the reason, and one suggested step from the teacher's own approved activities.
     */
    public function alerts(Request $request): JsonResponse
    {
        $all = $this->alerts->forTeacher($request->user()->teacher);
        $filter = in_array($request->query('filter'), ['support', 'start', 'up', 'quiet'], true) ? $request->query('filter') : 'all';

        return response()->json([
            'filter' => $filter,
            'counts' => [
                'all' => $all->count(),
                'support' => $all->where('kind', 'support')->count(),
                'start' => $all->where('kind', 'start')->count(),
                'up' => $all->where('kind', 'up')->count(),
                'quiet' => $all->where('kind', 'quiet')->count(),
            ],
            'alerts' => ($filter === 'all' ? $all : $all->where('kind', $filter))->map(fn (array $a) => $this->alertRow($a))->values(),
        ]);
    }

    /** "Mark handled": hides the alert until the learner's readings change. */
    public function handled(Request $request, Learner $learner): JsonResponse
    {
        $teacher = $request->user()->teacher;
        $kind = $request->validate(['kind' => ['required', 'in:support,start,up,quiet']])['kind'];

        $this->mineThisYear($teacher, $learner);
        $this->alerts->markHandled($teacher, $learner, $kind);

        return response()->json(['message' => "Marked handled for {$learner->first_name}."]);
    }

    /** Gives the suggested activity to this one learner (too long for them: refused with the reason) and marks the alert handled. */
    public function assignSuggested(Request $request, Learner $learner): JsonResponse
    {
        $teacher = $request->user()->teacher;
        $data = $request->validate([
            'kind' => ['required', 'in:support,start,up'],
            'activity_id' => ['required', 'integer'],
        ]);

        $this->mineThisYear($teacher, $learner);

        // Only the Teacher's own approved activities can ever be given from here.
        $activity = Activity::where('created_by_teacher_id', $teacher->id)->where('status', 'Approved')->find($data['activity_id']);
        abort_if($activity === null, 403, 'That activity is not one of your approved activities.');

        $this->alerts->assign($teacher, $learner, $activity, $data['kind']);

        return response()->json(['message' => "\"{$activity->title}\" assigned to {$learner->first_name}."]);
    }

    // ------------------------------------------------------------------ analytics

    /** The overview from the website: readings, average accuracy, reading levels in Phil-IRI words, words missed most, and what each assignment produced. */
    public function analytics(Request $request, TeacherInsights $insights): JsonResponse
    {
        $teacher = $request->user()->teacher;
        $period = is_string($request->query('period')) && array_key_exists($request->query('period'), TeacherInsights::PERIODS) ? $request->query('period') : 'week';
        $classChoice = $request->query('class') === 'all' ? null : ((int) $request->query('class') ?: null);

        $classes = $insights->classesFor($teacher, $classChoice);
        $o = $insights->overview($teacher, $classes, $period);

        return response()->json([
            'period' => $period,
            'periods' => TeacherInsights::PERIODS,
            'classes' => SchoolClass::where('teacher_id', $teacher->id)->orderByDesc('school_year')->orderBy('name')->get()->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'grade' => $c->grade_level, 'schoolYear' => $c->school_year])->values(),
            // Said only when the teacher picked one of their own classes; a class id that is not theirs falls back to all of them.
            'scope' => ($classChoice !== null && $classes->count() === 1 && $classes->first()->id === $classChoice) ? $classes->first()->name : 'All your classes',
            'learners' => $o['learners']->count(),
            'readings' => $o['readings'],
            'bySource' => ['teacher' => $o['bySource']['Teacher'], 'parent' => $o['bySource']['Parent']],
            'average' => $o['avg'],
            'change' => $o['delta'],
            'needSupport' => $o['needSupport'],
            'levels' => collect(['independent', 'instructional', 'frustration', 'non', 'unchecked'])->map(fn ($b) => ['key' => $b, 'label' => ReadingLevel::bandShort($b), 'count' => $o['bands'][$b]])->values(),
            'checked' => $o['checked'],
            'missedWords' => $o['missedWords'],
            'patterns' => $o['patterns'],
            'assignments' => $o['assignments']->map(fn (array $r) => ['title' => $r['activity']->title, 'for' => $r['for'], 'total' => $r['total'], 'done' => $r['done'], 'average' => $r['avg']])->values(),
        ]);
    }

    // ------------------------------------------------------------------ promotions

    /** Release and Claim: who can be released for the next grade (Grade 1 and 2 learners in this year's classes), and every learner waiting to be claimed. */
    public function promotions(Request $request): JsonResponse
    {
        $teacher = $request->user()->teacher;
        $classes = SchoolClass::where('teacher_id', $teacher->id)->where('school_year', SchoolClass::currentSchoolYear())->orderBy('name')->get();

        $pending = PromotionRecord::where('status', 'Pending')->with(['learner', 'releasedByTeacher.user'])->orderBy('released_at')->get();

        return response()->json([
            'release' => $classes->map(fn (SchoolClass $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'grade' => $c->grade_level,
                'learners' => $c->learners()->orderBy('first_name')->get()->map(fn (Learner $l) => [
                    'id' => $l->id,
                    'name' => trim($l->first_name.' '.$l->last_name),
                    'nextGrade' => (int) substr((string) $l->grade_level, 6) >= 3 ? null : 'Grade '.((int) substr((string) $l->grade_level, 6) + 1),
                ])->values(),
            ])->values(),
            // The queue is shared by every teacher; only the classes of the grade a learner moves up to can claim them.
            'claim' => $pending->map(fn (PromotionRecord $r) => [
                'id' => $r->id,
                'name' => trim(($r->learner?->first_name ?? '').' '.($r->learner?->last_name ?? '')),
                'nextGrade' => $r->next_grade,
                'releasedBy' => $r->releasedByTeacher?->user ? trim($r->releasedByTeacher->user->first_name.' '.$r->releasedByTeacher->user->last_name) : null,
                'releasedAt' => $r->released_at ? LearnerClock::local($r->released_at)->format('M j') : null,
                'classes' => $classes->where('grade_level', $r->next_grade)->whereIn('grade_level', $teacher->gradesAllowed())->map(fn (SchoolClass $c) => ['id' => $c->id, 'name' => $c->name])->values(),
                'note' => $classes->where('grade_level', $r->next_grade)->isNotEmpty() && ! in_array($r->next_grade, $teacher->gradesAllowed(), true)
                    ? "You do not handle {$r->next_grade}, so your {$r->next_grade} class cannot take new learners. Change the grades you handle in your profile if that is wrong."
                    : null,
            ])->values(),
            'claimable' => $pending->filter(fn (PromotionRecord $r) => $classes->where('grade_level', $r->next_grade)->whereIn('grade_level', $teacher->gradesAllowed())->isNotEmpty())->count(),
        ]);
    }

    public function release(Request $request, Learner $learner): JsonResponse
    {
        $record = $this->promotions->release($request->user()->teacher, $learner);

        return response()->json(['message' => "{$learner->first_name} released for promotion to {$record->next_grade}."]);
    }

    public function claim(Request $request, PromotionRecord $record): JsonResponse
    {
        $validated = $request->validate(['class_id' => ['required', 'integer']]);
        $done = $this->promotions->claim($request->user()->teacher, $record, (int) $validated['class_id']);

        return response()->json(['message' => "{$done['record']->learner->first_name} claimed into \"{$done['class']->name}\"."]);
    }

    // ------------------------------------------------------------------ helpers

    /** The learner must be in one of this Teacher's classes of the school year in progress. */
    private function mineThisYear(Teacher $teacher, Learner $learner): void
    {
        $ok = SchoolClass::where('teacher_id', $teacher->id)
            ->where('school_year', SchoolClass::currentSchoolYear())
            ->where('id', $learner->class_id)
            ->exists();

        abort_unless($ok, 404);
    }

    private function alertRow(array $a): array
    {
        $learner = $a['learner'];
        $card = $a['suggestion']['card'] ?? null;

        return [
            'kind' => $a['kind'],
            'variant' => $a['variant'] ?? null,
            'learnerId' => $learner->id,
            'classId' => $learner->class_id,
            'className' => $a['class']?->name,
            'name' => trim($learner->first_name.' '.$learner->last_name),
            'title' => $a['title'],
            'evidence' => $a['evidence'],
            'why' => $a['why'],
            'pattern' => $a['pattern'] ?? null,
            'basis' => $a['basis'] ?? null,
            'at' => $a['evidence_at']->toIso8601String(),
            'suggestion' => ($a['suggestion'] ?? null) ? [
                'level' => $a['suggestion']['tier'],
                'tip' => $a['suggestion']['tip'],
                'activity' => $card ? [
                    'id' => $card['activity']->id,
                    'title' => $card['activity']->title,
                    'words' => $card['activity']->word_count,
                    'level' => $card['tier'],
                    'skill' => $card['skill'],
                    'code' => $card['code'],
                    'why' => $card['why'],
                ] : null,
            ] : null,
        ];
    }
}
