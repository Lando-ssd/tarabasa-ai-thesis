<?php

namespace App\Http\Controllers;

use App\Models\Learner;
use App\Models\SchoolClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

        return view('teacher.classes.index', [
            'teacher' => $teacher,
            'classes' => $classes,
            'availableYears' => $availableYears,
            'selectedYear' => $selectedYear,
            'currentSchoolYear' => $currentSchoolYear,
            'isCurrentYear' => $selectedYear === $currentSchoolYear,
            'isPastYear' => $isPastYear,
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

        return redirect()
            ->route('teacher.classes.index', ['school_year' => $class->school_year])
            ->with('status', "Class \"{$class->name}\" updated.");
    }

    /**
     * Join a Learner — Teacher Actor Prompt Step 6: "input learnerCode +
     * target Class... the ONLY way a Teacher connects to a Learner."
     * A Learner already in a class (class_id not null) can't be joined
     * again elsewhere — the actor prompt's exact validation rule.
     */
    public function joinLearner(Request $request, SchoolClass $class): RedirectResponse
    {
        $teacher = $request->user()->teacher;

        abort_if($class->teacher_id !== $teacher->id, 403);
        abort_if(SchoolClass::isYearPast($class->school_year), 403, 'Past school year classes are read-only.');

        $validated = $request->validate([
            'learner_code' => ['required', 'string'],
        ]);

        $code = strtoupper(trim($validated['learner_code']));
        $learner = Learner::where('learner_code', $code)->first();

        if (! $learner) {
            return back()->withErrors(['learner_code' => 'No learner found with that code — double check and try again.'])->withInput();
        }

        if ($learner->class_id !== null) {
            return back()->withErrors(['learner_code' => 'This learner is already enrolled in a class.'])->withInput();
        }

        $learner->update(['class_id' => $class->id]);

        return redirect()
            ->route('teacher.classes.index', ['school_year' => $class->school_year])
            ->with('status', "{$learner->first_name} {$learner->last_name} added to \"{$class->name}\".");
    }
}
