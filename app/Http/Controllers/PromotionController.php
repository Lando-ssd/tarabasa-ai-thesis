<?php

namespace App\Http\Controllers;

use App\Models\Learner;
use App\Models\PromotionRecord;
use App\Models\SchoolClass;
use App\Services\TeacherPromotionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PromotionController extends Controller
{
    public function __construct(private readonly TeacherPromotionService $promotions) {}

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

        // Only this Teacher's own current-year classes of the required
        // next_grade are valid claim targets for each record.
        $matchingClassesByRecord = $pendingRecords->mapWithKeys(
            fn (PromotionRecord $record) => [
                $record->id => $myCurrentClasses->where('grade_level', $record->next_grade)->whereIn('grade_level', $teacher->gradesAllowed())->values(),
            ]
        );

        // A record whose grade this teacher has a class for but no longer handles: said on the screen instead of an empty list.
        $notHandled = $pendingRecords->mapWithKeys(
            fn (PromotionRecord $record) => [
                $record->id => $myCurrentClasses->where('grade_level', $record->next_grade)->isNotEmpty() && ! in_array($record->next_grade, $teacher->gradesAllowed(), true),
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
            'notHandled' => $notHandled,
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
        $record = $this->promotions->release($request->user()->teacher, $learner);

        return redirect()
            ->route('teacher.promotions.index')
            ->with('status', "{$learner->first_name} released for promotion to {$record->next_grade}.");
    }

    /**
     * Claim: the class and the learner's grade are both set from the claimed class (see TeacherPromotionService, which the phone app
     * calls too).
     */
    public function claim(Request $request, PromotionRecord $record): RedirectResponse
    {
        abort_if($record->status !== 'Pending', 403, 'This promotion has already been claimed.');

        $validated = $request->validate([
            'class_id' => ['required', 'integer'],
        ]);

        $done = $this->promotions->claim($request->user()->teacher, $record, (int) $validated['class_id']);

        return redirect()
            ->route('teacher.promotions.index', ['tab' => 'claim'])
            ->with('status', "{$done['record']->learner->first_name} claimed into \"{$done['class']->name}\".");
    }
}
