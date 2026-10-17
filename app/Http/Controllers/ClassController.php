<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityAssignment;
use App\Models\Learner;
use App\Models\SchoolClass;
use App\Services\ActivitySuggestions;
use App\Services\TeacherClassService;
use App\Support\ActivityFit;
use App\Support\GradeMatch;
use App\Support\ReadingLevel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClassController extends Controller
{
    public function __construct(private readonly TeacherClassService $classes) {}

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

        // The dropdown always offers the next school year too, so a class can be set up ahead.
        $nextStart = (int) explode('-', $currentSchoolYear)[0] + 1;
        $nextYear = $nextStart.'-'.($nextStart + 1);
        if (! $availableYears->contains($nextYear)) {
            $availableYears = $availableYears->push($nextYear)->sortByDesc(fn ($year) => $year)->values();
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

        $classActivities = $this->classes->activitiesByClass($teacher->id, $classes);

        // What the class cards and windows show about reading levels: the level mix, the reading
        // groups made automatically from each learner's latest level, the level-check flags and
        // the suggested activities. All computed here, read only; nothing is assigned by it.
        $suggestions = app(ActivitySuggestions::class);
        $insights = [];
        foreach ($classes as $c) {
            $groups = ReadingLevel::groups($c->learners);
            $hasLevels = collect($groups)->contains(fn ($g) => $g->isNotEmpty());
            $insights[$c->id] = [
                'mix' => ReadingLevel::mix($c->learners),
                'groups' => $groups,
                'flags' => ReadingLevel::levelChecks($c->learners),
                'hasLevels' => $hasLevels,
                'suggestions' => ($isPastYear || ! $hasLevels) ? [] : collect($groups)
                    ->map(fn ($g, $key) => $g->isEmpty() ? collect() : $suggestions->forGroup($teacher, $c, $key, $g))
                    ->all(),
                'starter' => ($isPastYear || $hasLevels) ? collect() : $suggestions->starterForClass($teacher, $c),
            ];
        }

        // What the page's script needs: the find box's index, and the assign dropdown's choices
        // (an activity a class already has, directly or through its group, is not offered again).
        $clientData = [
            'classes' => $classes->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'grade' => $c->grade_level, 'section' => $c->section, 'tag' => (string) $c->group_tag, 'sy' => $c->school_year])->values(),
            'learners' => $classes->flatMap(fn ($c) => $c->learners->map(fn ($l) => [
                'id' => $l->id, 'name' => $l->first_name.' '.$l->last_name, 'code' => $l->learner_code,
                'classId' => $c->id, 'className' => $c->name, 'sy' => $c->school_year,
            ]))->values(),
            'approved' => $approved->map(function (Activity $a) use ($classes, $isPastYear) {
                // How this activity fits each class (only when it is not a plain fit), so the Assign window can say so
                // before the button is pressed. The server checks again when it is pressed.
                $fit = [];
                if (! $isPastYear) {
                    foreach ($classes as $c) {
                        $f = GradeMatch::forAudience($a, $c->learners, 'this class', [$c]);
                        if (in_array($f['verdict'], [ActivityFit::BLOCKED, ActivityFit::CAUTION], true)) {
                            $fit[$c->id] = ['v' => $f['verdict'], 'n' => $f['note']];
                        }
                    }
                }

                return [
                    'id' => $a->id, 'title' => $a->title, 'tier' => $a->difficulty_tier, 'grade' => $a->grade_level,
                    'type' => $a->typeLabel(), 'words' => $a->word_count, 'passage' => $a->passage_text,
                    'long' => config("activity_levels.info.{$a->difficulty_tier}.long"),
                    'fit' => (object) $fit,
                ];
            })->values(),
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
            'insights' => $insights,
            'approvedActivities' => $approved,
            'levelInfo' => config('activity_levels.info'),
            'openClassId' => (int) $request->query('open', 0),
            'openTab' => (in_array($request->query('tab'), ['learners', 'groups', 'acts'], true) || preg_match('/^learner-\d+$/', (string) $request->query('tab')))
                ? $request->query('tab') : 'learners',
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

        $validated = $request->validate($this->classes->createRules($teacher), $this->classes->createMessages($teacher));

        $class = $this->classes->create($teacher, $validated);

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

        $validated = $request->validate($this->classes->updateRules($teacher, $class), $this->classes->updateMessages($teacher));

        $this->classes->update($class, $validated);

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
    public function joinLearner(Request $request, SchoolClass $class): RedirectResponse|JsonResponse
    {
        $teacher = $request->user()->teacher;

        abort_if($class->teacher_id !== $teacher->id, 403);
        abort_if(SchoolClass::isYearPast($class->school_year), 403, 'Past school year classes are read-only.');

        $validated = $request->validate([
            'learner_code' => ['required', 'string'],
        ], [
            'learner_code.required' => 'Type the last 5 characters of the Learner Code.',
        ]);

        // The rules (the last 5 characters or a whole code, the limit on wrong codes, one grade per class, the reading level
        // guard, telling the guardians) live in TeacherClassService, which the phone app calls too.
        $result = $this->classes->join($request->user(), $teacher, $class, $validated['learner_code']);

        if (! $result['ok']) {
            return $this->joinFailure($request, $result['message']);
        }

        $learner = $result['learner'];

        // The QR scanner adds a whole class in one go and reads this answer instead of following a redirect.
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'name' => "{$learner->first_name} {$learner->last_name}", 'code' => $learner->learner_code, 'learnerId' => $learner->id]);
        }

        // Open the new learner's own page straight away: what the parent shared and where the child reads now.
        return $this->backToClass($class, 'learner-'.$learner->id)
            ->with('status', "{$learner->first_name} {$learner->last_name} added to \"{$class->name}\". Here is what the parent shared.");
    }

    /** A refused code: back to the form with the message, or a JSON answer for the scanner. */
    private function joinFailure(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => false, 'message' => $message], 422);
        }

        return back()->withErrors(['learner_code' => $message])->withInput();
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
            // A reading group of this class (made automatically from reading levels): only the
            // learners who are in that group are given the activity. Left out, the whole class
            // gets it, including learners who join later.
            'reading_band' => ['nullable', Rule::in(array_keys(ReadingLevel::GROUPS))],
            // Suggestions on the Activities page send the teacher back there instead of here.
            'return' => ['nullable', Rule::in(['activities'])],
            'activity_id' => ['required', 'integer'],
        ], [
            'activity_id.required' => 'Choose an activity to assign.',
        ]);
        $band = $validated['reading_band'] ?? null;

        // The rules (not given twice, can the learners read it, the reading group) live in TeacherActivityService, which the phone
        // app calls too.
        $done = app(\App\Services\TeacherActivityService::class)->assignToClass($teacher, $class, (int) $validated['activity_id'], $band);

        if ($done['blocked'] !== null) {
            if (($validated['return'] ?? null) === 'activities') {
                return redirect()->route('teacher.activities.index', ['tab' => 'mine'])->with('classError', $done['blocked']);
            }

            throw ValidationException::withMessages(['activity_id' => $done['blocked']]);
        }

        $message = $done['message'];

        if (($validated['return'] ?? null) === 'activities') {
            return redirect()->route('teacher.activities.index', ['tab' => 'mine'])->with('status', $message);
        }

        return $this->backToClass($class, $band ? 'groups' : 'acts')->with('status', $message);
    }

    /**
     * Move a learner to another of this Teacher's classes in the same school year. The system only
     * suggests this (the level check on the roster); the Teacher decides. The learner's own grade
     * is not touched: moving a strong reader to a higher class is a teaching decision, not a promotion.
     */
    public function moveLearner(Request $request, SchoolClass $class, Learner $learner): RedirectResponse
    {
        $teacher = $request->user()->teacher;

        $validated = $request->validate(['to_class_id' => ['required', 'integer']], [
            'to_class_id.required' => 'Choose the class to move to.',
        ]);

        $target = $this->classes->move($teacher, $class, $learner, (int) $validated['to_class_id']);

        return $this->backToClass($class, 'learners')->with('status', "{$learner->first_name} {$learner->last_name} moved to \"{$target->name}\".");
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
}
