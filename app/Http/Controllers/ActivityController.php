<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityAssignment;
use App\Models\Learner;
use App\Models\OpenRepositoryListing;
use App\Models\SchoolClass;
use App\Services\ActivityAiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ActivityController extends Controller
{
    /**
     * Generate Activity — Teacher Actor Prompt Step 7. Deliberately has NO
     * 'teacher.active' middleware: a Pending Teacher can use this (their 2
     * free credits), per Step 3 — only Class Management and other
     * "touching real students" actions are gated by Active status.
     */
    public function create(Request $request): View
    {
        $teacher = $request->user()->teacher;

        return view('teacher.activities.generate', [
            'teacher' => $teacher,
            'competencies' => config('activity_competencies.competencies'),
            'activityTypeLabels' => config('activity_competencies.activity_type_labels'),
            'maxVariants' => config('activity_competencies.max_variants_per_level'),
        ]);
    }

    /**
     * Calls the real, deployed gemini_activity_gen service — no mock, no
     * local generation logic. Credits are checked BEFORE calling (Step
     * 7.1) and decremented by exactly 1 only on a successful generation
     * (Step 7.6) — a failed/timed-out call must not cost the Teacher a
     * credit for nothing.
     */
    public function generate(Request $request, ActivityAiClient $activityAi): RedirectResponse
    {
        $teacher = $request->user()->teacher;

        if ($teacher->free_generation_credits_remaining <= 0) {
            $reason = $teacher->status === 'Active'
                ? 'Share an approved activity to the Repository to earn 2 more.'
                : 'More free credits unlock once your school verification is approved.';

            throw ValidationException::withMessages([
                'credits' => "You're out of free generation credits. {$reason}",
            ]);
        }

        $competencies = config('activity_competencies.competencies');

        $validated = $request->validate([
            'grade_level' => ['required', Rule::in(['Grade 1', 'Grade 2', 'Grade 3'])],
            'competency' => ['required', Rule::in(array_keys($competencies))],
            'activity_type' => ['required', 'string'],
            'activity_count' => ['required', 'integer', 'min:1', 'max:5'],
            'topic' => ['nullable', 'string', 'max:200'],
            'teacher_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // The API rejects an activity_type that isn't mapped to the chosen
        // competency (422) — check it here too, so the Teacher gets an
        // immediate, specific message instead of a round-trip failure.
        $allowedTypes = $competencies[$validated['competency']]['activity_types'];
        if (! in_array($validated['activity_type'], $allowedTypes, true)) {
            throw ValidationException::withMessages([
                'activity_type' => 'That activity type isn\'t available for the selected competency.',
            ]);
        }

        $gradeNumber = (int) substr($validated['grade_level'], 6);

        try {
            $data = $activityAi->generateBundle([
                'grade' => $gradeNumber,
                'competency' => $validated['competency'],
                'activity_type' => $validated['activity_type'],
                'variants_per_level' => $validated['activity_count'],
                'topic' => $validated['topic'] ?? null,
                'teacher_notes' => $validated['teacher_notes'] ?? null,
            ]);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['grade_level' => $e->getMessage()]);
        }

        Activity::createManyFromBundle($data, [
            'created_by_teacher_id' => $teacher->id,
            'grade_level' => $validated['grade_level'],
            'competency' => $validated['competency'],
            'competency_label' => $data['competency_label'] ?? $competencies[$validated['competency']]['label'],
            'activity_type' => $validated['activity_type'],
            'topic' => $validated['topic'] ?? null,
            'teacher_notes' => $validated['teacher_notes'] ?? null,
        ]);

        $teacher->decrement('free_generation_credits_remaining');

        return redirect()
            ->route('teacher.activities.index')
            ->with('status', "Generated {$data['total_activities']} draft activities — review them below.");
    }

    /**
     * My Activities — Teacher Actor Prompt Step 8. Three sections: Drafts
     * Awaiting Review, Approved, Rejected.
     */
    public function index(Request $request): View
    {
        $teacher = $request->user()->teacher;

        $activities = Activity::where('created_by_teacher_id', $teacher->id)
            ->with('assignments.learner', 'assignments.schoolClass', 'repositoryListing.ratings')
            ->orderByDesc('id')
            ->get()
            ->groupBy('status');

        // Assign targets — current/future classes only (past years are
        // read-only everywhere else in this app; assigning work into a
        // closed-out historical class makes no sense).
        $assignClasses = SchoolClass::where('teacher_id', $teacher->id)
            ->get()
            ->reject(fn ($class) => SchoolClass::isYearPast($class->school_year))
            ->values();

        $assignLearners = Learner::whereIn('class_id', $assignClasses->pluck('id'))
            ->orderBy('first_name')
            ->get();

        $assignGroupTags = $assignClasses->pluck('group_tag')->filter()->unique()->values();

        return view('teacher.activities.index', [
            'teacher' => $teacher,
            'drafts' => $activities->get('Draft', collect()),
            'approved' => $activities->get('Approved', collect()),
            'rejected' => $activities->get('Rejected', collect()),
            'activityTypeLabels' => config('activity_competencies.activity_type_labels'),
            'assignClasses' => $assignClasses,
            'assignLearners' => $assignLearners,
            'assignGroupTags' => $assignGroupTags,
        ]);
    }

    /**
     * Approve a Draft as-is — Step 8's first Draft action.
     */
    public function approve(Request $request, Activity $activity): RedirectResponse
    {
        $this->authorizeOwnership($request, $activity);
        abort_if($activity->status !== 'Draft', 403, 'Only a Draft can be approved.');

        $activity->update(['status' => 'Approved']);

        return back()->with('status', "\"{$activity->title}\" approved.");
    }

    /**
     * Edit a Draft's content, then approve in the SAME action — per Step 8:
     * "Saving moves status → Approved in the same action." There is no
     * separate "save without approving" state.
     */
    public function update(Request $request, Activity $activity): RedirectResponse
    {
        $this->authorizeOwnership($request, $activity);
        abort_if($activity->status !== 'Draft', 403, 'Only a Draft can be edited.');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:140'],
            'instructions' => ['required', 'string', 'max:500'],
            'passage_text' => ['required', 'string', 'max:6000'],
        ]);

        $activity->update([
            ...$validated,
            // reference_text (used for real AI-scoring alignment, Slice 2
            // onward) must stay in sync with passage_text — found while
            // building Slice 2 that this wasn't happening, which would
            // have silently scored a Learner's reading against stale,
            // pre-edit text. Same whitespace-normalization the API itself
            // does to derive reference_text from display_text.
            'reference_text' => preg_replace('/\s+/', ' ', trim($validated['passage_text'])),
            'status' => 'Approved',
        ]);

        return redirect()
            ->route('teacher.activities.index')
            ->with('status', "\"{$activity->title}\" updated and approved.");
    }

    /**
     * Reject a Draft — Step 8's third Draft action.
     */
    public function reject(Request $request, Activity $activity): RedirectResponse
    {
        $this->authorizeOwnership($request, $activity);
        abort_if($activity->status !== 'Draft', 403, 'Only a Draft can be rejected.');

        $activity->update(['status' => 'Rejected']);

        return back()->with('status', "\"{$activity->title}\" rejected.");
    }

    /**
     * Assign — Teacher Actor Prompt Step 8: choose exactly ONE of a
     * specific Learner, a whole Class, or a Group (group_tag shared across
     * this Teacher's classes). Only an Approved Activity can be assigned;
     * every target ID is re-verified as actually belonging to this
     * Teacher server-side, never trusted just because the form only
     * listed their own options.
     */
    public function assign(Request $request, Activity $activity): RedirectResponse
    {
        $this->authorizeOwnership($request, $activity);
        abort_if($activity->status !== 'Approved', 403, 'Only an Approved activity can be assigned.');

        $teacher = $request->user()->teacher;

        $validated = $request->validate([
            'assign_learner_id' => ['nullable', 'integer'],
            'assign_class_id' => ['nullable', 'integer'],
            'assign_group_tag' => ['nullable', 'string'],
        ]);

        $targets = array_filter([
            'learner_id' => $validated['assign_learner_id'] ?? null,
            'class_id' => $validated['assign_class_id'] ?? null,
            'group_tag' => $validated['assign_group_tag'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        if (count($targets) !== 1) {
            throw ValidationException::withMessages([
                'assign_target' => 'Choose exactly one: a Learner, a Class, or a Group.',
            ]);
        }

        if (isset($targets['learner_id'])) {
            $learner = Learner::whereHas('schoolClass', fn ($q) => $q->where('teacher_id', $teacher->id))
                ->find($targets['learner_id']);
            abort_if(! $learner, 403, 'That learner is not in one of your classes.');
        }

        if (isset($targets['class_id'])) {
            $class = SchoolClass::where('teacher_id', $teacher->id)->find($targets['class_id']);
            abort_if(! $class, 403, 'That class is not yours.');
            abort_if(SchoolClass::isYearPast($class->school_year), 403, 'Past school year classes are read-only.');
        }

        if (isset($targets['group_tag'])) {
            $hasTag = SchoolClass::where('teacher_id', $teacher->id)
                ->where('group_tag', $targets['group_tag'])
                ->exists();
            abort_if(! $hasTag, 403, 'That group tag is not used by any of your classes.');
        }

        ActivityAssignment::create([
            'activity_id' => $activity->id,
            'learner_id' => $targets['learner_id'] ?? null,
            'class_id' => $targets['class_id'] ?? null,
            'group_tag' => $targets['group_tag'] ?? null,
            'assigned_by_teacher_id' => $teacher->id,
        ]);

        return redirect()
            ->route('teacher.activities.index')
            ->with('status', "\"{$activity->title}\" assigned.");
    }

    /**
     * Share to Repository — Teacher Actor Prompt Step 8: the second
     * required action on an Approved card, alongside Assign. Free or
     * Paid (with a price if Paid); on success credits +2 and can only
     * ever happen once per Activity. This is the real mechanism behind
     * copy that's already live elsewhere in the app (Generate Activity's
     * credit banner: "Share an approved activity to the Repository to
     * earn 2 more").
     */
    public function shareToRepository(Request $request, Activity $activity): RedirectResponse
    {
        $this->authorizeOwnership($request, $activity);
        abort_if($activity->status !== 'Approved', 403, 'Only an Approved activity can be shared.');
        abort_if($activity->shared_to_repository, 403, 'This activity has already been shared.');

        $validated = $request->validate([
            'price_type' => ['required', Rule::in(['Free', 'Paid'])],
            'price' => ['required_if:price_type,Paid', 'nullable', 'numeric', 'min:0.01'],
        ]);

        $teacher = $request->user()->teacher;

        OpenRepositoryListing::create([
            'activity_id' => $activity->id,
            'teacher_id' => $teacher->id,
            'price_type' => $validated['price_type'],
            'price' => $validated['price_type'] === 'Paid' ? $validated['price'] : 0,
        ]);

        $activity->update(['shared_to_repository' => true]);
        $teacher->increment('free_generation_credits_remaining', 2);

        return redirect()
            ->route('teacher.activities.index')
            ->with('status', "\"{$activity->title}\" shared to the Repository — +2 credits.");
    }

    private function authorizeOwnership(Request $request, Activity $activity): void
    {
        abort_if($activity->created_by_teacher_id !== $request->user()->teacher->id, 403);
    }
}
