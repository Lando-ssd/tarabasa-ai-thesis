<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityAssignment;
use App\Models\Learner;
use App\Models\SchoolClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClassController extends Controller
{
    /**
     * Class Management — Teacher Actor Prompt, Step 6.
     * Viewable by a Pending Teacher (locked/explained) or an Active one
     * (full access) — the 'teacher.active' middleware only guards the
     * mutating routes, not this one, per the Admin Actor Prompt's rule
     * that approval gates "touching real students," not visibility.
     */
    public function index(Request $request): View
    {
        $teacher = $request->user()->teacher;
        $currentSchoolYear = SchoolClass::currentSchoolYear();

        $availableYears = SchoolClass::where('teacher_id', $teacher->id)
            ->select('school_year')
            ->distinct()
            ->orderByDesc('school_year')
            ->pluck('school_year')
            ->values();

        // The current year is always selectable, even before the Teacher
        // has created a single class in it yet.
        if (! $availableYears->contains($currentSchoolYear)) {
            $availableYears = $availableYears->push($currentSchoolYear)->sortByDesc(fn ($year) => $year)->values();
        }

        $selectedYear = $request->query('school_year', $currentSchoolYear);
        if (! $availableYears->contains($selectedYear)) {
            $selectedYear = $currentSchoolYear;
        }

        $classes = SchoolClass::where('teacher_id', $teacher->id)
            ->where('school_year', $selectedYear)
            ->with(['learners.promotionRecords.releasedFromClass', 'learners.readingSessions'])
            ->orderBy('name')
            ->get();

        // Only a year strictly BEFORE the current one is a locked historical
        // record. A future year (a class set up ahead of time, per the
        // SchoolYear patch) is not "current" but must stay fully editable —
        // it's the opposite of a past year, not a second flavor of it.
        $isPastYear = SchoolClass::isYearPast($selectedYear);

        $approved = Activity::where('created_by_teacher_id', $teacher->id)
            ->where('status', 'Approved')
            ->orderBy('grade_level')
            ->orderBy('title')
            ->get();

        $classActivities = $this->activitiesByClass($teacher->id, $classes);

        // What the page's script needs: the find box's index, and the assign dropdown's choices
        // (an activity a class already has, directly or through its group, is not offered again).
        $clientData = [
            'classes' => $classes->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'grade' => $c->grade_level, 'section' => $c->section, 'tag' => (string) $c->group_tag, 'sy' => $c->school_year])->values(),
            'learners' => $classes->flatMap(fn ($c) => $c->learners->map(fn ($l) => [
                'id' => $l->id, 'name' => $l->first_name.' '.$l->last_name, 'code' => $l->learner_code,
                'classId' => $c->id, 'className' => $c->name, 'sy' => $c->school_year,
            ]))->values(),
            'approved' => $approved->map(fn (Activity $a) => [
                'id' => $a->id, 'title' => $a->title, 'tier' => $a->difficulty_tier, 'grade' => $a->grade_level,
                'type' => $a->typeLabel(), 'words' => $a->word_count, 'passage' => $a->passage_text,
                'long' => config("activity_levels.info.{$a->difficulty_tier}.long"),
            ])->values(),
            'taken' => collect($classActivities)->map(fn ($rows) => $rows->map(fn ($r) => $r['activity']->id)->values())->all(),
        ];

        return view('teacher.classes.index', [
            'clientData' => $clientData,
            'teacher' => $teacher,
            'classes' => $classes,
            'availableYears' => $availableYears,
            'selectedYear' => $selectedYear,
            'currentSchoolYear' => $currentSchoolYear,
            'isCurrentYear' => $selectedYear === $currentSchoolYear,
            'isPastYear' => $isPastYear,
            'classActivities' => $classActivities,
            'approvedActivities' => $approved,
            'levelInfo' => config('activity_levels.info'),
            'openClassId' => (int) $request->query('open', 0),
            'openTab' => in_array($request->query('tab'), ['learners', 'acts'], true) ? $request->query('tab') : 'learners',
        ]);
    }

    /**
     * Guarded by 'teacher.active' middleware — a Pending Teacher's request
     * never reaches this method; EnsureTeacherIsActive rejects it first.
     * A Class is never edited into "becoming" next year's class — every
     * school year is always a brand-new row (SchoolYear_Addition.txt).
     */
    public function store(Request $request): RedirectResponse
    {
        $teacher = $request->user()->teacher;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'grade_level' => ['required', Rule::in(['Grade 1', 'Grade 2', 'Grade 3'])],
            'section' => ['required', 'string', 'max:255'],
            'group_tag' => ['nullable', 'string', 'max:255'],
            'school_year' => ['required', 'string', 'max:20'],
        ]);

        $class = SchoolClass::create([
            'teacher_id' => $teacher->id,
            ...$validated,
        ]);

        return redirect()
            ->route('teacher.classes.index', ['school_year' => $class->school_year])
            ->with('status', "Class \"{$class->name}\" created.");
    }

    /**
     * Edit an existing class — Teacher Actor Prompt Step 6: current and
     * future school years stay fully editable, past years never do.
     * School Year itself is deliberately not editable here — a Class is
     * never edited into "becoming" next year's class (Step 6), so changing
     * years means creating a new Class via store(), not updating this one.
     * Guarded by 'teacher.active' middleware plus an explicit ownership +
     * past-year check here, since route-model binding alone doesn't stop
     * a Teacher from passing another Teacher's class ID.
     */
    public function update(Request $request, SchoolClass $class): RedirectResponse
    {
        $teacher = $request->user()->teacher;

        abort_if($class->teacher_id !== $teacher->id, 403);
        abort_if(SchoolClass::isYearPast($class->school_year), 403, 'Past school year classes are read-only.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'grade_level' => ['required', Rule::in(['Grade 1', 'Grade 2', 'Grade 3'])],
            'section' => ['required', 'string', 'max:255'],
            'group_tag' => ['nullable', 'string', 'max:255'],
        ]);

        $class->update($validated);

        return $this->backToClass($class, 'learners')->with('status', "Class \"{$class->name}\" updated.");
    }

    /**
     * Join a Learner — Teacher Actor Prompt Step 6: "input learnerCode +
     * target Class... the ONLY way a Teacher connects to a Learner."
     * A Learner already in a class (class_id not null) can't be joined
     * again elsewhere — the actor prompt's exact validation rule.
     *
     * Every code starts with TB-, so the window only asks for the last 5 characters; a whole
     * pasted code (TB-12345) is accepted too.
     */
    public function joinLearner(Request $request, SchoolClass $class): RedirectResponse
    {
        $teacher = $request->user()->teacher;

        abort_if($class->teacher_id !== $teacher->id, 403);
        abort_if(SchoolClass::isYearPast($class->school_year), 403, 'Past school year classes are read-only.');

        $validated = $request->validate([
            'learner_code' => ['required', 'string'],
        ], [
            'learner_code.required' => 'Type the last 5 characters of the Learner Code.',
        ]);

        $tail = preg_replace('/^TB-?/', '', strtoupper(trim($validated['learner_code'])));

        if (! preg_match('/^[A-Z0-9]{5}$/', $tail)) {
            return back()->withErrors(['learner_code' => 'The Learner Code ends in 5 characters, like TB-12345.'])->withInput();
        }

        $learner = Learner::where('learner_code', 'TB-'.$tail)->first();

        if (! $learner) {
            return back()->withErrors(['learner_code' => 'No learner found with that code. Check it and try again.'])->withInput();
        }

        if ($learner->class_id !== null) {
            return back()->withErrors(['learner_code' => 'This learner is already enrolled in a class.'])->withInput();
        }

        $learner->update(['class_id' => $class->id]);

        return $this->backToClass($class, 'learners')
            ->with('status', "{$learner->first_name} {$learner->last_name} added to \"{$class->name}\".");
    }

    /**
     * Assign one of the Teacher's Approved activities to this whole class, from the class window.
     * The same record (an ActivityAssignment with a class_id) the Activities screen's Assign
     * creates, so the Learner side needs nothing new: every learner in the class can read it.
     */
    public function assignActivity(Request $request, SchoolClass $class): RedirectResponse
    {
        $teacher = $request->user()->teacher;

        abort_if($class->teacher_id !== $teacher->id, 403);
        abort_if(SchoolClass::isYearPast($class->school_year), 403, 'Past school year classes are read-only.');

        $validated = $request->validate([
            'activity_id' => ['required', 'integer'],
        ], [
            'activity_id.required' => 'Choose an activity to assign.',
        ]);

        $activity = Activity::where('created_by_teacher_id', $teacher->id)
            ->where('status', 'Approved')
            ->find($validated['activity_id']);

        if (! $activity) {
            throw ValidationException::withMessages(['activity_id' => 'Choose one of your approved activities.']);
        }

        if (ActivityAssignment::where('activity_id', $activity->id)->where('class_id', $class->id)->exists()) {
            throw ValidationException::withMessages(['activity_id' => 'That activity is already assigned to this class.']);
        }

        ActivityAssignment::create([
            'activity_id' => $activity->id,
            'class_id' => $class->id,
            'assigned_by_teacher_id' => $teacher->id,
        ]);

        return $this->backToClass($class, 'acts')->with('status', "\"{$activity->title}\" assigned to {$class->name}.");
    }

    /** Back to the Classes screen with the same class window open again, on the right tab. */
    private function backToClass(SchoolClass $class, string $tab): RedirectResponse
    {
        return redirect()->route('teacher.classes.index', [
            'school_year' => $class->school_year,
            'open' => $class->id,
            'tab' => $tab,
        ]);
    }

    /**
     * What each class has been given: activities assigned to the class itself, and ones assigned
     * to a group tag the class carries. Only Approved activities count. A class assignment wins
     * over a group one for the same activity.
     *
     * @return array<int, Collection<int, array{activity: Activity, via: string, tag: ?string, on: mixed}>>
     */
    private function activitiesByClass(int $teacherId, Collection $classes): array
    {
        $assignments = ActivityAssignment::where('assigned_by_teacher_id', $teacherId)
            ->where(fn ($q) => $q->whereNotNull('class_id')->orWhereNotNull('group_tag'))
            ->with('activity')
            ->orderBy('assigned_at')
            ->get()
            ->filter(fn (ActivityAssignment $a) => $a->activity && $a->activity->status === 'Approved');

        $byClass = [];

        foreach ($classes as $class) {
            $rows = collect();

            foreach ($assignments as $a) {
                if ($a->class_id === $class->id) {
                    $rows->put($a->activity_id, ['activity' => $a->activity, 'via' => 'class', 'tag' => null, 'on' => $a->assigned_at]);
                } elseif ($class->group_tag && $a->group_tag === $class->group_tag && ! $rows->has($a->activity_id)) {
                    $rows->put($a->activity_id, ['activity' => $a->activity, 'via' => 'group', 'tag' => $a->group_tag, 'on' => $a->assigned_at]);
                }
            }

            $byClass[$class->id] = $rows->values();
        }

        return $byClass;
    }
}
