<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Learner;
use App\Models\SchoolClass;
use App\Services\TeacherAlerts;
use App\Services\TeacherClassService;
use App\Support\ClassLevel;
use App\Support\LearnerProfile;
use App\Support\ReadingLevel;
use App\Support\TeacherNav;
use App\Support\TeacherRoster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The phone app's Teacher screens. Thin JSON wrappers over the same services and support classes the website's Teacher
 * pages use (TeacherClassService, TeacherAlerts, ReadingLevel, ClassLevel, LearnerProfile), so the two cannot disagree.
 *
 * Reading is open to a Pending Teacher (approval gates "touching real students", not looking around), exactly like the
 * website; every route that changes something carries 'teacher.active' (see routes/api.php), which answers a Pending
 * Teacher with a JSON 403 and the website's own wording.
 */
class TeacherApiController extends Controller
{
    public function __construct(private readonly TeacherClassService $classes) {}

    /** Home: who needs attention and the numbers behind "Waiting for you". */
    public function home(Request $request): JsonResponse
    {
        $user = $request->user();
        $teacher = $user->teacher;

        $support = app(TeacherAlerts::class)->forTeacher($teacher, withSuggestions: false)->where('kind', 'support')->values();
        $activities = Activity::where('created_by_teacher_id', $teacher->id)->get(['id', 'status']);
        $classes = SchoolClass::where('teacher_id', $teacher->id)->where('school_year', SchoolClass::currentSchoolYear())->withCount('learners')->get();
        $counts = TeacherNav::counts($user);

        return response()->json([
            'schoolYear' => SchoolClass::currentSchoolYear(),
            'counts' => [
                'classes' => $classes->count(),
                'learners' => (int) $classes->sum('learners_count'),
                'activities' => $activities->count(),
                'approved' => $activities->where('status', 'Approved')->count(),
                'drafts' => $activities->where('status', 'Draft')->count(),
                'alerts' => $counts['unread'],
                'claim' => $counts['claim'],
                'credits' => (int) $teacher->free_generation_credits_remaining,
            ],
            // One line per child who needs support, the same words as the website's banner.
            'needsSupport' => $support->take(3)->map(fn (array $a) => ['name' => trim($a['learner']->first_name.' '.$a['learner']->last_name), 'title' => $a['title'], 'why' => $a['why']])->values(),
            'needsSupportCount' => $support->count(),
        ]);
    }

    /** This school year's classes (or another year's, read only), with what the Classes screen's cards show. */
    public function classes(Request $request): JsonResponse
    {
        $teacher = $request->user()->teacher;
        $current = SchoolClass::currentSchoolYear();
        $year = (string) $request->query('school_year', $current);

        $classes = SchoolClass::where('teacher_id', $teacher->id)->where('school_year', $year)->with('learners')->orderBy('name')->get();
        $byClass = $this->classes->activitiesByClass($teacher->id, $classes);

        // Every school year this teacher has classes in, newest first, with this one always among them (so the picker never loses it).
        $years = SchoolClass::where('teacher_id', $teacher->id)->distinct()->pluck('school_year')->push($current)->unique()->sortDesc()->values();

        return response()->json([
            'schoolYear' => $year,
            'currentSchoolYear' => $current,
            'years' => $years,
            'readOnly' => SchoolClass::isYearPast($year),
            'grades' => $teacher->gradesAllowed(),
            'classes' => $classes->map(fn (SchoolClass $c) => TeacherRoster::classCard($c, $byClass))->values(),
        ]);
    }

    /** One class: its learners (with a tag for those who need you), its reading groups and what it has been given. */
    public function showClass(Request $request, SchoolClass $class): JsonResponse
    {
        $teacher = $request->user()->teacher;
        $this->mine($request, $class);

        $class->load('learners.readingSessions');
        $tags = TeacherRoster::tags($teacher);
        $flags = ReadingLevel::levelChecks($class->learners);
        $byClass = $this->classes->activitiesByClass($teacher->id, collect([$class]));
        $general = ClassLevel::general($class);

        return response()->json([
            'class' => TeacherRoster::classCard($class, $byClass),
            // Putting a learner in needs an Active Teacher, a class from this year onward, and a grade the Teacher still handles.
            'canAdd' => $teacher->status === 'Active' && ! SchoolClass::isYearPast($class->school_year) && in_array($class->grade_level, $teacher->gradesAllowed(), true),
            'generalLevel' => ['label' => $general['label'], 'source' => $general['source']],
            'learners' => $class->learners->sortBy(fn (Learner $l) => mb_strtolower($l->last_name.' '.$l->first_name))->map(fn (Learner $l) => TeacherRoster::learner($l, $tags, $flags))->values(),
            'activities' => collect($byClass[$class->id] ?? [])->map(fn (array $row) => [
                'id' => $row['activity']->id,
                'title' => $row['activity']->title,
                'level' => $row['activity']->difficulty_tier,
                'via' => $row['via'],
                'band' => $row['band'] ? (ReadingLevel::GROUPS[$row['band']]['title'] ?? $row['band']) : null,
                'tag' => $row['tag'],
            ])->values(),
        ]);
    }

    /** A new class (Active Teachers only). The same rules, and the same messages, as the website. */
    public function storeClass(Request $request): JsonResponse
    {
        $teacher = $request->user()->teacher;

        $validated = $request->validate($this->classes->createRules($teacher), $this->classes->createMessages($teacher));
        $class = $this->classes->create($teacher, $validated)->load('learners');

        return response()->json(['class' => TeacherRoster::classCard($class, [])], 201);
    }

    public function updateClass(Request $request, SchoolClass $class): JsonResponse
    {
        $teacher = $request->user()->teacher;
        $this->mine($request, $class, writing: true);

        $validated = $request->validate($this->classes->updateRules($teacher, $class), $this->classes->updateMessages($teacher));
        $this->classes->update($class, $validated);

        return response()->json(['class' => TeacherRoster::classCard($class->load('learners'), $this->classes->activitiesByClass($teacher->id, collect([$class])))]);
    }

    /**
     * Put a learner in this class from a code typed or scanned on the phone. Answers {ok: true, learner} or, with a 422,
     * {ok: false, message} in the website's own words (wrong grade, already in a class, the reading level guard, too many tries).
     */
    public function joinLearner(Request $request, SchoolClass $class): JsonResponse
    {
        $user = $request->user();
        $this->mine($request, $class, writing: true);

        $validated = $request->validate(
            ['learner_code' => ['required', 'string', 'max:30']],
            ['learner_code.required' => 'Type the last 5 characters of the Learner Code.'],
        );

        $result = $this->classes->join($user, $user->teacher, $class, $validated['learner_code']);

        if (! $result['ok']) {
            return response()->json(['ok' => false, 'message' => $result['message']], 422);
        }

        $tags = TeacherRoster::tags($user->teacher);

        return response()->json(['ok' => true, 'learner' => TeacherRoster::learner($result['learner'], $tags)], 201);
    }

    /** One learner's page: what the parent shared, where the child reads now, and what suits them (the website's learner window). */
    public function showLearner(Request $request, SchoolClass $class, Learner $learner): JsonResponse
    {
        $teacher = $request->user()->teacher;
        $this->mine($request, $class);
        abort_if($learner->class_id !== $class->id, 404);

        $class->load('learners');

        // Moving a child takes an Active teacher and a school year in progress; the choices are this teacher's other classes of the child's grade.
        $canMove = $teacher->status === 'Active' && ! SchoolClass::isYearPast($class->school_year);

        return response()->json([
            'learner' => TeacherRoster::learner($learner, TeacherRoster::tags($teacher), ReadingLevel::levelChecks($class->learners)),
            'profile' => LearnerProfile::forTeacher($learner),
            'canMove' => $canMove,
            'moveTargets' => $canMove ? $this->classes->moveTargets($teacher, $class, $learner)->map(fn (SchoolClass $c) => ['id' => $c->id, 'name' => $c->name, 'grade' => $c->grade_level, 'section' => $c->section])->values() : [],
        ]);
    }

    /** Move a learner to another of this teacher's classes of the same year. A refusal comes back as a 422 under `to_class_id`, in the website's words. */
    public function moveLearner(Request $request, SchoolClass $class, Learner $learner): JsonResponse
    {
        $this->mine($request, $class, writing: true);
        $validated = $request->validate(['to_class_id' => ['required', 'integer']], ['to_class_id.required' => 'Choose the class to move to.']);

        $target = $this->classes->move($request->user()->teacher, $class, $learner, (int) $validated['to_class_id']);

        return response()->json(['message' => "{$learner->first_name} {$learner->last_name} moved to \"{$target->name}\".", 'class' => ['id' => $target->id, 'name' => $target->name]]);
    }

    /** The class must be this Teacher's own, and (to change it) not from a past school year. A forged id gets the same 404 as a missing one. */
    private function mine(Request $request, SchoolClass $class, bool $writing = false): void
    {
        abort_if($class->teacher_id !== $request->user()->teacher->id, 404);
        abort_if($writing && SchoolClass::isYearPast($class->school_year), 403, 'Past school year classes are read-only.');
    }
}
