<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivityAssignment;
use App\Models\ActivityGeneration;
use App\Models\Learner;
use App\Models\OpenRepositoryListing;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Support\ActivityFit;
use App\Support\GradeMatch;
use App\Support\GenerationBasis;
use App\Support\ReadingLevel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * What a Teacher does to an activity: put it in a level, reject or restore it, edit a draft, give it to learners, share it to
 * the Repository, and ask for new ones.
 *
 * The website's ActivityController and the phone app's Api\TeacherActivityApiController both call this, so the two cannot disagree
 * about who may assign what (an activity too long for the learners cannot be forced through), what a move does, or what a
 * generation costs. A refused action throws an HTTP exception (403) or a ValidationException with the website's own words.
 */
class TeacherActivityService
{
    /** The message a successful move or approval shows. */
    public function approvedMessage(Activity $activity): string
    {
        return $activity->movedByTeacher()
            ? "Approved in {$activity->difficulty_tier}. The AI suggested {$activity->ai_difficulty_tier}. Your choice is saved."
            : "Approved in {$activity->difficulty_tier}.";
    }

    /**
     * Put an activity in a level, or reject it. A Draft in a level is approved there; an Approved one is re-leveled; only a Draft
     * can be rejected. The level the AI suggested is never overwritten, so a move is always visible (and is told to the AI the next
     * time the Teacher generates).
     *
     * @return array{message: string, undo: bool} undo is true when the move can be taken back (a reject)
     */
    public function place(Activity $activity, string $level): array
    {
        if ($level === 'reject') {
            abort_if($activity->status !== 'Draft', 403, 'Only a Draft can be rejected.');

            $activity->update(['status' => 'Rejected']);

            return ['message' => "\"{$activity->title}\" rejected.", 'undo' => true];
        }

        abort_if($activity->status === 'Rejected', 403, 'Restore a rejected activity before placing it in a level.');

        $wasDraft = $activity->status === 'Draft';

        $activity->update(['status' => 'Approved', 'difficulty_tier' => $level]);

        return ['message' => $wasDraft ? $this->approvedMessage($activity) : "Moved to {$activity->difficulty_tier}.", 'undo' => false];
    }

    /** Approve a Draft at the level it already has (the AI's suggestion), or at the level given. */
    public function approve(Activity $activity, ?string $level = null): string
    {
        abort_if($activity->status !== 'Draft', 403, 'Only a Draft can be approved.');

        $activity->update(['status' => 'Approved', 'difficulty_tier' => $level ?? $activity->difficulty_tier]);

        return $this->approvedMessage($activity);
    }

    public function reject(Activity $activity): string
    {
        abort_if($activity->status !== 'Draft', 403, 'Only a Draft can be rejected.');

        $activity->update(['status' => 'Rejected']);

        return "\"{$activity->title}\" rejected.";
    }

    /** Bring a rejected activity back to "To review" (also what Undo does after a reject). */
    public function restore(Activity $activity): string
    {
        abort_if($activity->status !== 'Rejected', 403, 'Only a rejected activity can be restored.');

        $activity->update(['status' => 'Draft']);

        return "\"{$activity->title}\" is back in To review.";
    }

    public function editRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:140'],
            'instructions' => ['required', 'string', 'max:500'],
            'passage_text' => ['required', 'string', 'max:6000'],
            'level' => ['nullable', Rule::in(config('activity_levels.tiers'))],
        ];
    }

    /**
     * Edit a Draft's content, then approve in the SAME action (Step 8: "Saving moves status to Approved in the same action"). The
     * level is the one currently chosen.
     *
     * @param  array<string, mixed>  $validated  already checked with editRules()
     */
    public function edit(Activity $activity, array $validated): string
    {
        abort_if($activity->status !== 'Draft', 403, 'Only a Draft can be edited.');

        $activity->update([
            'title' => $validated['title'],
            'instructions' => $validated['instructions'],
            'passage_text' => $validated['passage_text'],
            // reference_text (the text a reading is scored against) must stay in sync with passage_text, or a later reading would
            // be scored against stale, pre-edit text.
            'reference_text' => preg_replace('/\s+/', ' ', trim($validated['passage_text'])),
            'word_count' => Activity::countWords($validated['passage_text']),
            'difficulty_tier' => $validated['level'] ?? $activity->difficulty_tier,
            'status' => 'Approved',
        ]);

        return "\"{$activity->title}\" updated and approved in {$activity->difficulty_tier}.";
    }

    /**
     * Assign (Teacher Actor Prompt Step 8): exactly ONE of a specific Learner, a whole Class, or a Group (a group tag shared across
     * this Teacher's classes). Only an Approved activity can be assigned. Every target is re-verified as this Teacher's, and an
     * activity far too long for the learners who would receive it is refused (ActivityFit), whichever screen it was given from.
     *
     * @return string the message to show; it carries a note when the activity is a stretch for some of the learners
     */
    public function assign(Teacher $teacher, Activity $activity, ?int $learnerId, ?int $classId, ?string $groupTag): string
    {
        abort_if($activity->status !== 'Approved', 403, 'Only an Approved activity can be assigned.');

        $targets = array_filter([
            'learner_id' => $learnerId,
            'class_id' => $classId,
            'group_tag' => $groupTag,
        ], fn ($value) => $value !== null && $value !== '');

        if (count($targets) !== 1) {
            throw ValidationException::withMessages([
                'assign_target' => 'Choose exactly one: a Learner, a Class, or a Group.',
            ]);
        }

        if (isset($targets['learner_id'])) {
            $learner = Learner::whereHas('schoolClass', fn ($q) => $q->where('teacher_id', $teacher->id))->find($targets['learner_id']);
            abort_if(! $learner, 403, 'That learner is not in one of your classes.');
        }

        if (isset($targets['class_id'])) {
            $class = SchoolClass::where('teacher_id', $teacher->id)->find($targets['class_id']);
            abort_if(! $class, 403, 'That class is not yours.');
            abort_if(SchoolClass::isYearPast($class->school_year), 403, 'Past school year classes are read-only.');
        }

        if (isset($targets['group_tag'])) {
            $hasTag = SchoolClass::where('teacher_id', $teacher->id)->where('group_tag', $targets['group_tag'])->exists();
            abort_if(! $hasTag, 403, 'That group tag is not used by any of your classes.');
        }

        // A grade is only for its own grade (GradeMatch): a Grade 2 activity cannot go to a Grade 1 learner or class, or to a group tag that
        // reaches a class of another grade, even when the teacher handles both grades. Checked first, whichever screen it was given from.
        if (isset($targets['learner_id'])) {
            $fit = GradeMatch::forLearner($activity, $learner);
        } elseif (isset($targets['class_id'])) {
            $fit = GradeMatch::forAudience($activity, $class->learners()->get(), $class->name, [$class]);
        } else {
            $tagged = SchoolClass::where('teacher_id', $teacher->id)->where('group_tag', $targets['group_tag'])->get();
            $audience = Learner::whereHas('schoolClass', fn ($q) => $q->where('teacher_id', $teacher->id)->where('group_tag', $targets['group_tag']))->get();
            $fit = GradeMatch::forAudience($activity, $audience, 'the '.$targets['group_tag'].' group', $tagged);
        }

        if ($fit['verdict'] === ActivityFit::BLOCKED) {
            throw ValidationException::withMessages(['assign_target' => $fit['note']]);
        }

        ActivityAssignment::create([
            'activity_id' => $activity->id,
            'learner_id' => $targets['learner_id'] ?? null,
            'class_id' => $targets['class_id'] ?? null,
            'group_tag' => $targets['group_tag'] ?? null,
            'assigned_by_teacher_id' => $teacher->id,
        ]);

        return "\"{$activity->title}\" assigned.".($fit['verdict'] === ActivityFit::CAUTION ? ' Note: '.$fit['note'] : '');
    }

    /**
     * Give an approved activity to a whole class, or to one reading group of it (made automatically from reading levels: only the
     * learners in that group get it). The same record (an ActivityAssignment with a class_id) the Activities screen's Assign creates,
     * so the Learner side needs nothing new. Given to the whole class already covers every group. An activity far too long for the
     * learners who would receive it comes back as `blocked` with the reason, never as an assignment.
     *
     * @return array{blocked: ?string, message: ?string, band: ?string}
     */
    public function assignToClass(Teacher $teacher, SchoolClass $class, int $activityId, ?string $band): array
    {
        abort_if($class->teacher_id !== $teacher->id, 403);
        abort_if(SchoolClass::isYearPast($class->school_year), 403, 'Past school year classes are read-only.');

        $activity = Activity::where('created_by_teacher_id', $teacher->id)->where('status', 'Approved')->find($activityId);

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

        // Can the learners who would receive it read it? Decided here, on the server, so the note on the screen cannot be skipped.
        $learners = $class->learners()->get();
        if ($band) {
            $learners = ReadingLevel::groups($learners)[$band];
        }
        $audience = $band ? 'the '.strtolower(ReadingLevel::GROUPS[$band]['title']).' group' : 'this class';
        $fit = GradeMatch::forAudience($activity, $learners, $audience, [$class]);

        if ($fit['verdict'] === ActivityFit::BLOCKED) {
            return ['blocked' => $fit['note'], 'message' => null, 'band' => $band];
        }

        ActivityAssignment::create([
            'activity_id' => $activity->id,
            'class_id' => $class->id,
            'reading_band' => $band,
            'assigned_by_teacher_id' => $teacher->id,
        ]);

        $who = $band ? 'the '.strtolower(ReadingLevel::GROUPS[$band]['title']).' group in '.$class->name : $class->name;

        return ['blocked' => null, 'message' => "\"{$activity->title}\" assigned to {$who}.".($fit['verdict'] === ActivityFit::CAUTION ? ' Note: '.$fit['note'] : ''), 'band' => $band];
    }

    public function shareRules(): array
    {
        return [
            'price_type' => ['required', Rule::in(['Free', 'Paid'])],
            'price' => ['required_if:price_type,Paid', 'nullable', 'numeric', 'min:0.01'],
        ];
    }

    /**
     * Share to the Repository (Step 8): free or paid, once for each activity, and 2 free credits for the Teacher. Done under a lock
     * and checked again inside it: two taps at the same moment used to both pass the first check, list the activity twice and pay
     * out the 2 credits twice.
     *
     * @param  array<string, mixed>  $validated  already checked with shareRules()
     */
    public function share(Teacher $teacher, Activity $activity, array $validated): string
    {
        abort_if($activity->status !== 'Approved', 403, 'Only an Approved activity can be shared.');
        abort_if($activity->shared_to_repository, 403, 'This activity has already been shared.');

        $shared = DB::transaction(function () use ($activity, $teacher, $validated) {
            $fresh = Activity::whereKey($activity->id)->lockForUpdate()->first();
            if ($fresh === null || $fresh->shared_to_repository) {
                return false;
            }

            OpenRepositoryListing::create([
                'activity_id' => $activity->id,
                'teacher_id' => $teacher->id,
                'price_type' => $validated['price_type'],
                'price' => $validated['price_type'] === 'Paid' ? $validated['price'] : 0,
            ]);

            $fresh->update(['shared_to_repository' => true]);
            $teacher->increment('free_generation_credits_remaining', 2);

            return true;
        });

        abort_unless($shared, 403, 'This activity has already been shared.');

        return "\"{$activity->title}\" shared to the Repository. You earned 2 free credits.";
    }

    // ------------------------------------------------------------------ asking for new activities

    public function generateRules(): array
    {
        $max = (int) config('activity_levels.max_per_level');

        return [
            'grade_level' => ['required', Rule::in(['Grade 1', 'Grade 2', 'Grade 3'])],
            'competency' => ['required', Rule::in(array_keys(config('activity_competencies.competencies')))],
            'activity_type' => ['required', 'string'],
            'levels' => ['required', 'array'],
            'levels.Easy' => ['nullable', 'integer', 'min:0', "max:{$max}"],
            'levels.Medium' => ['nullable', 'integer', 'min:0', "max:{$max}"],
            'levels.Hard' => ['nullable', 'integer', 'min:0', "max:{$max}"],
            'topic' => ['nullable', 'string', 'max:200'],
            'teacher_notes' => ['nullable', 'string', 'max:1000'],
            'class_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * A class a request may be written for ("write it for my class"): the teacher's own, from the current school year, of the grade
     * asked for. Anything else is refused with a plain message (a forged id gets the same answer as a wrong grade).
     */
    public function classForGeneration(int $teacherId, int $classId, string $grade): SchoolClass
    {
        $class = SchoolClass::where('teacher_id', $teacherId)->find($classId);

        if (! $class || SchoolClass::isYearPast($class->school_year)) {
            throw ValidationException::withMessages(['class_id' => 'Choose one of your classes from this school year.']);
        }

        if ($class->grade_level !== $grade) {
            throw ValidationException::withMessages(['class_id' => "{$class->name} is a {$class->grade_level} class. Pick {$class->grade_level} as the grade, or choose a {$grade} class."]);
        }

        return $class;
    }

    /**
     * Saves a request for new activities and starts writing them in the background (the AI takes one to several minutes, far too
     * long to hold a request open). Credits are checked BEFORE (Step 7.1) and taken, exactly one, only when the activities have been
     * saved (Step 7.6), so a failed or timed out request never costs one. Only one request at a time for a Teacher.
     *
     * With "confirm" off (the website) nothing about the response is decided here: the caller reads the returned generation.
     *
     * @param  array<string, mixed>  $input  the request's fields
     */
    public function startGeneration(Teacher $teacher, array $input): ActivityGeneration
    {
        if ($teacher->free_generation_credits_remaining <= 0) {
            $reason = $teacher->status === 'Active'
                ? 'Share an approved activity to the Repository to earn 2 more.'
                : 'More free credits unlock once your school verification is approved.';

            throw ValidationException::withMessages(['credits' => "You're out of free generation credits. {$reason}"]);
        }

        $competencies = config('activity_competencies.competencies');
        $validated = Validator::make($input, $this->generateRules())->validate();

        $wanted = collect(config('activity_levels.tiers'))
            ->mapWithKeys(fn (string $tier) => [$tier => (int) ($validated['levels'][$tier] ?? 0)])
            ->all();

        if (array_sum($wanted) === 0) {
            throw ValidationException::withMessages(['levels' => 'Choose at least one level to generate.']);
        }

        // The grade must be one this teacher handles: the window only offers those, and this stops a request made another way.
        if ($refusal = GradeMatch::teacherRefusal($teacher, $validated['grade_level'], 'write an activity')) {
            throw ValidationException::withMessages(['grade_level' => $refusal]);
        }

        // The API rejects an activity_type that isn't mapped to the chosen competency (422): check it here too, so the Teacher gets an
        // immediate, specific message.
        if (! in_array($validated['activity_type'], $competencies[$validated['competency']]['activity_types'], true)) {
            throw ValidationException::withMessages(['activity_type' => 'That activity type isn\'t available for the selected skill.']);
        }

        // "Write it for my class": the basis is worked out here, on the server, from the class's own readings, never taken from input.
        $basis = null;
        $class = null;
        if (! empty($validated['class_id'])) {
            $class = $this->classForGeneration($teacher->id, (int) $validated['class_id'], $validated['grade_level']);
            $basis = GenerationBasis::forClass($class, $validated['activity_type']);
        }

        // One at a time: a second request now could spend a credit the first one still needs. The check and the new request are made
        // together under a lock on the teacher's row, so two taps at the same moment cannot both get through.
        $generation = DB::transaction(function () use ($teacher, $validated, $wanted, $class, $basis) {
            Teacher::whereKey($teacher->id)->lockForUpdate()->first();

            if (ActivityGeneration::activeFor($teacher->id)) {
                return null;
            }

            return ActivityGeneration::create([
                'teacher_id' => $teacher->id,
                'class_id' => $class?->id,
                'status' => ActivityGeneration::QUEUED,
                'grade_level' => $validated['grade_level'],
                'competency' => $validated['competency'],
                'activity_type' => $validated['activity_type'],
                'topic' => $validated['topic'] ?? null,
                'teacher_notes' => $validated['teacher_notes'] ?? null,
                'basis' => $basis,
                'levels' => $wanted,
            ]);
        });

        if ($generation === null) {
            throw ValidationException::withMessages([
                'generate' => 'Your last request is still being written. It shows at the top of Activities. Wait for it to finish, then ask for more.',
            ]);
        }

        // Normally this only queues the work and returns at once. If the queue is set to run inline (QUEUE_CONNECTION=sync), it runs
        // here and the result is known right away.
        try {
            \App\Jobs\GenerateActivitiesJob::dispatch($generation->id);
        } catch (\Throwable $e) {
            Log::error('Could not run the activity generation', ['generation' => $generation->id, 'error' => $e->getMessage()]);
        }

        return $generation->refresh();
    }
}
