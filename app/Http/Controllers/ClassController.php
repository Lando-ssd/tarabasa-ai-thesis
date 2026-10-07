<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityAssignment;
use App\Models\Learner;
use App\Models\SchoolClass;
use App\Services\ActivitySuggestions;
use App\Support\ActivityFit;
use App\Support\ReadingLevel;
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

        $classActivities = $this->activitiesByClass($teacher->id, $classes);

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
                        $f = ActivityFit::forAudience($a, $c->learners, 'this class');
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

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // Only the grades the Teacher said they handle (Profile). Enforced here, so a forged
            // request cannot open a class for another grade.
            'grade_level' => ['required', Rule::in($teacher->gradesAllowed())],
            'section' => ['required', 'string', 'max:255'],
            'group_tag' => ['nullable', 'string', 'max:255'],
            'school_year' => ['required', 'string', 'max:20'],
        ], [
            'grade_level.in' => 'You handle '.implode(' and ', $teacher->gradesAllowed()).'. Change the grades you handle in Profile to open a class for another grade.',
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
            // The class's own grade stays allowed even if the Teacher later narrowed the grades
            // they handle, so an old class can still be renamed.
            'grade_level' => ['required', Rule::in(array_unique([...$teacher->gradesAllowed(), $class->grade_level]))],
            'section' => ['required', 'string', 'max:255'],
            'group_tag' => ['nullable', 'string', 'max:255'],
        ], [
            'grade_level.in' => 'You handle '.implode(' and ', $teacher->gradesAllowed()).'. Change the grades you handle in Profile to use another grade.',
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

        // The last 5 characters (the 4 digits and check digit of TB26-48293, or the 5 digits of an
        // older TB-12345), or the whole code. See App\Support\LearnerCode.
        // A wrong code is limited per teacher: the last 5 characters are only about ten thousand
        // possibilities, and a teacher who tried them all would see every unenrolled child's name.
        $joinKey = 'join-learner|teacher:'.$teacher->id;
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($joinKey, 10)) {
            $minutes = (int) ceil(\Illuminate\Support\Facades\RateLimiter::availableIn($joinKey) / 60);

            return back()->withErrors(['learner_code' => "Too many codes that did not match. Please wait {$minutes} ".($minutes === 1 ? 'minute' : 'minutes').' and try again.'])->withInput();
        }

        $typed = trim($validated['learner_code']);
        $tail = strtoupper(preg_replace('/^TB[0-9]{0,2}-?/i', '', preg_replace('/\s+/', '', $typed)));

        if (! \App\Support\LearnerCode::looksLikeCode($typed) && ! preg_match('/^[A-Z0-9]{5}$/', $tail)) {
            return back()->withErrors(['learner_code' => 'Type the last 5 characters of the Learner Code, like 48293.'])->withInput();
        }

        $matches = \App\Support\LearnerCode::matching(\App\Support\LearnerCode::looksLikeCode($typed) ? $typed : $tail);

        if ($matches->count() > 1) {
            return back()->withErrors(['learner_code' => 'More than one learner ends in those characters. Type the whole code, like TB26-48293.'])->withInput();
        }

        $learner = $matches->first();

        if (! $learner) {
            \Illuminate\Support\Facades\RateLimiter::hit($joinKey, 900);

            return back()->withErrors(['learner_code' => 'No learner found with that code. Check it and try again.'])->withInput();
        }

        if ($learner->class_id !== null) {
            \Illuminate\Support\Facades\RateLimiter::hit($joinKey, 900);

            return back()->withErrors(['learner_code' => 'This learner is already enrolled in a class.'])->withInput();
        }

        $learner->update(['class_id' => $class->id]);

        // The child's guardians are told, so a child is never added to a class without them knowing.
        \App\Models\Notification::notifyForLearner(
            $learner,
            \App\Models\Notification::TYPE_CLASS_JOINED,
            "{$learner->first_name} was added to the class \"{$class->name}\" by {$request->user()->first_name} {$request->user()->last_name}".($teacher->school_name ? " ({$teacher->school_name})" : '').'.',
            includeTeacher: false
        );

        // Open the new learner's own page straight away: what the parent shared and where the child reads now.
        return $this->backToClass($class, 'learner-'.$learner->id)
            ->with('status', "{$learner->first_name} {$learner->last_name} added to \"{$class->name}\". Here is what the parent shared.");
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

        $activity = Activity::where('created_by_teacher_id', $teacher->id)
            ->where('status', 'Approved')
            ->find($validated['activity_id']);

        if (! $activity) {
            throw ValidationException::withMessages(['activity_id' => 'Choose one of your approved activities.']);
        }

        // Already given to the whole class covers every group too.
        $already = ActivityAssignment::where('activity_id', $activity->id)->where('class_id', $class->id)
            ->where(fn ($q) => $q->whereNull('reading_band')->when($band, fn ($x) => $x->orWhere('reading_band', $band)))
            ->exists();

        if ($already) {
            throw ValidationException::withMessages(['activity_id' => $band
                ? 'That activity is already given to this reading group.'
                : 'That activity is already assigned to this class.']);
        }

        // Can the learners who would receive it read it? Decided here, on the server, so the note on the screen
        // cannot be skipped: an activity far too long for them cannot be forced through (see ActivityFit).
        $learners = $class->learners()->get();
        if ($band) {
            $learners = ReadingLevel::groups($learners)[$band];
        }
        $audience = $band ? 'the '.strtolower(ReadingLevel::GROUPS[$band]['title']).' group' : 'this class';
        $fit = ActivityFit::forAudience($activity, $learners, $audience);

        if ($fit['verdict'] === ActivityFit::BLOCKED) {
            if (($validated['return'] ?? null) === 'activities') {
                return redirect()->route('teacher.activities.index', ['tab' => 'mine'])->with('classError', $fit['note']);
            }

            throw ValidationException::withMessages(['activity_id' => $fit['note']]);
        }

        ActivityAssignment::create([
            'activity_id' => $activity->id,
            'class_id' => $class->id,
            'reading_band' => $band,
            'assigned_by_teacher_id' => $teacher->id,
        ]);

        $who = $band ? 'the '.strtolower(ReadingLevel::GROUPS[$band]['title']).' group in '.$class->name : $class->name;
        $message = "\"{$activity->title}\" assigned to {$who}.".($fit['verdict'] === ActivityFit::CAUTION ? ' Note: '.$fit['note'] : '');

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

        abort_if($class->teacher_id !== $teacher->id, 403);
        abort_if(SchoolClass::isYearPast($class->school_year), 403, 'Past school year classes are read-only.');
        abort_if($learner->class_id !== $class->id, 404);

        $validated = $request->validate(['to_class_id' => ['required', 'integer']], [
            'to_class_id.required' => 'Choose the class to move to.',
        ]);

        $target = SchoolClass::where('teacher_id', $teacher->id)
            ->where('school_year', $class->school_year)
            ->where('id', '!=', $class->id)
            ->find($validated['to_class_id']);

        if (! $target) {
            throw ValidationException::withMessages(['to_class_id' => 'Choose one of your other classes.']);
        }

        $learner->update(['class_id' => $target->id]);

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
                if ($a->class_id === $class->id && $a->reading_band === null) {
                    $rows->put($a->activity_id.'|', ['activity' => $a->activity, 'via' => 'class', 'tag' => null, 'band' => null, 'on' => $a->assigned_at]);
                } elseif ($a->class_id === $class->id) {
                    $rows->put($a->activity_id.'|'.$a->reading_band, ['activity' => $a->activity, 'via' => 'band', 'tag' => null, 'band' => $a->reading_band, 'on' => $a->assigned_at]);
                } elseif ($class->group_tag && $a->group_tag === $class->group_tag && ! $rows->has($a->activity_id.'|')) {
                    $rows->put($a->activity_id.'|', ['activity' => $a->activity, 'via' => 'group', 'tag' => $a->group_tag, 'band' => null, 'on' => $a->assigned_at]);
                }
            }

            // Given to the whole class (or through a focus group) already covers a reading group.
            $rows = $rows->reject(fn ($row, $key) => $row['via'] === 'band' && $rows->has($row['activity']->id.'|'));

            $byClass[$class->id] = $rows->values();
        }

        return $byClass;
    }
}
