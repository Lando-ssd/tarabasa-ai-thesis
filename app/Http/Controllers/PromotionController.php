<?php

namespace App\Http\Controllers;

use App\Models\Learner;
use App\Models\PromotionRecord;
use App\Models\SchoolClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PromotionController extends Controller
{
    /**
     * Grade Promotions — Teacher Actor Prompt Step 9. Release/Claim tabs,
     * viewable while Pending (same rule as Class Management/Analytics —
     * approval gates touching real students, not visibility); only the
     * mutating release()/claim() actions are Active-gated via
     * 'teacher.active' on the routes themselves.
     */
    public function index(Request $request): View
    {
        $teacher = $request->user()->teacher;
        $currentSchoolYear = SchoolClass::currentSchoolYear();

        $myCurrentClasses = SchoolClass::where('teacher_id', $teacher->id)
            ->where('school_year', $currentSchoolYear)
            ->orderBy('name')
            ->get();

        $releasableLearners = Learner::whereIn('class_id', $myCurrentClasses->pluck('id'))
            ->orderBy('first_name')
            ->get()
            ->groupBy('class_id');

        // Step 9: "a shared, platform-wide queue of every Pending
        // PromotionRecord — never a name search, never limited to ones
        // this Teacher released." Every Pending record shows, regardless
        // of whether this Teacher has a matching class for it.
        $pendingRecords = PromotionRecord::where('status', 'Pending')
            ->with(['learner', 'releasedByTeacher.user'])
            ->orderBy('released_at')
            ->get();

        // Only this Teacher's own current-year classes matching the
        // required next_grade are valid claim targets for each record.
        $matchingClassesByRecord = $pendingRecords->mapWithKeys(
            fn (PromotionRecord $record) => [
                $record->id => $myCurrentClasses->where('grade_level', $record->next_grade)->values(),
            ]
        );

        // The badge count is Teacher-specific ("this needs you"), not the
        // raw platform-wide total — confirmed with the user: showing a
        // count that includes records this Teacher can't act on would be
        // misleading, even though the queue itself still lists everyone.
        $actionableCount = $matchingClassesByRecord->filter(fn ($classes) => $classes->isNotEmpty())->count();

        return view('teacher.promotions', [
            'teacher' => $teacher,
            'tab' => $request->query('tab') === 'claim' ? 'claim' : 'release',
            'myCurrentClasses' => $myCurrentClasses,
            'releasableLearners' => $releasableLearners,
            'pendingRecords' => $pendingRecords,
            'matchingClassesByRecord' => $matchingClassesByRecord,
            'actionableCount' => $actionableCount,
        ]);
    }

    /**
     * Release: clears classId, creates a Pending PromotionRecord. The
     * Learner stays fully Active, just unassigned until claimed by any
     * Teacher (possibly a different one, possibly the same one next
     * year) with a matching class.
     */
    public function release(Request $request, Learner $learner): RedirectResponse
    {
        $teacher = $request->user()->teacher;
        $class = $learner->schoolClass;

        abort_unless($class && $class->teacher_id === $teacher->id, 403, 'That learner is not in one of your classes.');
        abort_if(SchoolClass::isYearPast($class->school_year), 403, 'Past school year classes are read-only.');

        $gradeNumber = (int) substr($learner->grade_level, 6);
        abort_if($gradeNumber >= 3, 403, 'Grade 3 has no next grade — this Learner cannot be released for promotion.');

        $nextGrade = 'Grade '.($gradeNumber + 1);

        PromotionRecord::create([
            'learner_id' => $learner->id,
            'released_by_teacher_id' => $teacher->id,
            'next_grade' => $nextGrade,
            'status' => 'Pending',
            'released_from_class_id' => $class->id,
        ]);

        $learner->update(['class_id' => null]);

        return redirect()
            ->route('teacher.promotions.index')
            ->with('status', "{$learner->first_name} released for promotion to {$nextGrade}.");
    }

    /**
     * Claim: classId AND gradeLevel both set from the claimed class —
     * gradeLevel is never manually typed anywhere in this flow. The
     * claimed class is re-verified server-side (ownership, grade match,
     * current school year) — never trusted just because the form only
     * listed valid options.
     */
    public function claim(Request $request, PromotionRecord $record): RedirectResponse
    {
        $teacher = $request->user()->teacher;

        abort_if($record->status !== 'Pending', 403, 'This promotion has already been claimed.');

        $validated = $request->validate([
            'class_id' => ['required', 'integer'],
        ]);

        $class = SchoolClass::where('teacher_id', $teacher->id)
            ->where('id', $validated['class_id'])
            ->where('grade_level', $record->next_grade)
            ->where('school_year', SchoolClass::currentSchoolYear())
            ->first();

        abort_unless($class, 403, 'That class is not a valid claim target.');

        $record->update([
            'claimed_by_teacher_id' => $teacher->id,
            'status' => 'Claimed',
            'claimed_at' => now(),
            'claimed_into_class_id' => $class->id,
        ]);

        $record->learner->update([
            'class_id' => $class->id,
            'grade_level' => $class->grade_level,
        ]);

        return redirect()
            ->route('teacher.promotions.index', ['tab' => 'claim'])
            ->with('status', "{$record->learner->first_name} claimed into \"{$class->name}\".");
    }
}
