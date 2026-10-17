<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivityGeneration;
use App\Models\Learner;
use App\Models\SchoolClass;
use App\Services\TeacherActivityService;
use App\Support\ActivityFit;
use App\Support\GradeMatch;
use App\Support\ReadingLevel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The phone app's Activities screens: the review list (drafts waiting to be put in a level), My activities, one activity, and
 * asking for new ones. Thin JSON wrappers over TeacherActivityService, the same code the website's ActivityController calls, so
 * the two cannot disagree about what a move does or who may be given what.
 *
 * Looking is open to a Pending Teacher (and so is asking for new activities with the free credits, per Teacher Actor Prompt
 * Step 3); everything that touches real students (assigning, sharing, placing in a level) needs an Active one, which the
 * 'teacher.active' middleware on those routes answers with a JSON 403.
 */
class TeacherActivityApiController extends Controller
{
    /** The most rows one list answers with. A teacher's own activities are a few dozen, so one screenful of pages is never needed. */
    private const LIMIT = 150;

    public function __construct(private readonly TeacherActivityService $activities) {}

    /** The list: drafts to review, or approved or rejected ones, with the counts for every tab and the notice for a running request. */
    public function index(Request $request): JsonResponse
    {
        $teacher = $request->user()->teacher;
        $all = Activity::where('created_by_teacher_id', $teacher->id)->with('assignments')->orderByDesc('id')->get();
        $counts = $all->countBy('status');

        $status = in_array($request->query('status'), ['Draft', 'Approved', 'Rejected'], true)
            ? $request->query('status')
            : ($counts->get('Draft', 0) > 0 ? 'Draft' : 'Approved');

        $tier = in_array($request->query('tier'), config('activity_levels.tiers'), true) ? $request->query('tier') : null;
        $query = mb_strtolower(trim((string) $request->query('q', '')));

        $rows = $all->where('status', $status)
            ->when($tier, fn ($c) => $c->where('difficulty_tier', $tier))
            ->when($query !== '', fn ($c) => $c->filter(fn (Activity $a) => str_contains(mb_strtolower($a->title.' '.$a->grade_level.' '.$a->typeLabel().' '.$a->topic), $query)))
            ->take(self::LIMIT)
            ->values();

        return response()->json([
            'status' => $status,
            'counts' => ['Draft' => $counts->get('Draft', 0), 'Approved' => $counts->get('Approved', 0), 'Rejected' => $counts->get('Rejected', 0)],
            'tiers' => config('activity_levels.tiers'),
            'locked' => $teacher->status !== 'Active',
            'credits' => (int) $teacher->free_generation_credits_remaining,
            'generation' => ($notice = ActivityGeneration::noticeFor($teacher->id)) ? $notice->toStatus() : null,
            'activities' => $rows->map(fn (Activity $a) => $this->row($a, withPassage: $status === 'Draft'))->values(),
        ]);
    }

    /** One activity: the text, why it is in its level, who it was given to, and where it can be given. */
    public function show(Request $request, Activity $activity): JsonResponse
    {
        $teacher = $request->user()->teacher;
        $this->mine($request, $activity);

        $activity->load('assignments.learner', 'assignments.schoolClass', 'repositoryListing.ratings');
        $info = config("activity_levels.info.{$activity->difficulty_tier}");
        $band = $activity->levelBand();
        $listing = $activity->repositoryListing;

        $payload = $this->row($activity, withPassage: true) + [
            'instructions' => $activity->instructions,
            'competency' => $activity->competency_label,
            'topic' => $activity->topic,
            'features' => $activity->reading_features ?? [],
            'levelMeaning' => $info['long'] ?? null,
            'band' => $band ? ['min' => $band[0], 'max' => $band[1]] : null,
            'lengthFits' => $activity->lengthFitsLevel(),
            'basis' => $activity->generation_basis,
            'givenTo' => $activity->assignments->map(fn ($as) => $this->assignmentLabel($as))->values(),
            'listing' => $listing ? [
                'price' => $listing->price_type === 'Free' ? 'Free' : '₱'.number_format($listing->price),
                'stars' => $listing->ratings->isNotEmpty() ? round($listing->ratings->avg('rating'), 1) : null,
                'ratings' => $listing->ratings->count(),
            ] : null,
        ];

        // Where an approved activity can be given, and how well it suits each place (ActivityFit): said next to each choice, and
        // checked again on the server when Assign is pressed.
        if ($activity->status === 'Approved') {
            $classes = SchoolClass::where('teacher_id', $teacher->id)->orderBy('name')->get()->reject(fn ($c) => SchoolClass::isYearPast($c->school_year))->values();
            $learners = Learner::whereIn('class_id', $classes->pluck('id'))->with(['schoolClass', 'readingSessions'])->orderBy('first_name')->get();
            $tags = $classes->pluck('group_tag')->filter()->unique()->values();
            $fit = fn (array $f) => ['verdict' => $f['verdict'], 'note' => $f['note'] ?? null, 'reason' => $f['reason'] ?? null];

            $payload['targets'] = [
                'classes' => $classes->map(fn (SchoolClass $c) => ['id' => $c->id, 'name' => $c->name, 'grade' => $c->grade_level, 'fit' => $fit(GradeMatch::forAudience($activity, $learners->where('class_id', $c->id), $c->name, [$c]))])->values(),
                'groups' => $tags->map(fn ($tag) => ['tag' => $tag, 'fit' => $fit(GradeMatch::forAudience($activity, $learners->whereIn('class_id', $classes->where('group_tag', $tag)->pluck('id')), 'the '.$tag.' group', $classes->where('group_tag', $tag)))])->values(),
                'learners' => $learners->map(fn (Learner $l) => ['id' => $l->id, 'name' => trim($l->first_name.' '.$l->last_name), 'className' => $l->schoolClass?->name, 'fit' => $fit(GradeMatch::forLearner($activity, $l))])->values(),
            ];
        }

        return response()->json($payload);
    }

    /** Put it in a level (a draft is approved there), or reject it. `undo` says the move can be taken back with restore. */
    public function place(Request $request, Activity $activity): JsonResponse
    {
        $this->mine($request, $activity);
        $validated = $request->validate(['level' => ['required', Rule::in([...config('activity_levels.tiers'), 'reject'])]]);

        $done = $this->activities->place($activity, $validated['level']);

        return response()->json(['message' => $done['message'], 'undo' => $done['undo'], 'activity' => $this->row($activity->refresh())]);
    }

    public function restore(Request $request, Activity $activity): JsonResponse
    {
        $this->mine($request, $activity);

        return response()->json(['message' => $this->activities->restore($activity), 'activity' => $this->row($activity->refresh())]);
    }

    /** Edit a draft's words and approve it in the same action (Step 8). */
    public function edit(Request $request, Activity $activity): JsonResponse
    {
        $this->mine($request, $activity);
        abort_if($activity->status !== 'Draft', 403, 'Only a Draft can be edited.');

        $message = $this->activities->edit($activity, $request->validate($this->activities->editRules()));

        return response()->json(['message' => $message, 'activity' => $this->row($activity->refresh())]);
    }

    /** Give it to one learner, one class or one focus group. Too long for the learners who would get it: refused with the reason. */
    public function assign(Request $request, Activity $activity): JsonResponse
    {
        $this->mine($request, $activity);

        $validated = $request->validate([
            'learner_id' => ['nullable', 'integer'],
            'class_id' => ['nullable', 'integer'],
            'group_tag' => ['nullable', 'string', 'max:255'],
        ]);

        $message = $this->activities->assign(
            $request->user()->teacher,
            $activity,
            isset($validated['learner_id']) ? (int) $validated['learner_id'] : null,
            isset($validated['class_id']) ? (int) $validated['class_id'] : null,
            $validated['group_tag'] ?? null,
        );

        return response()->json(['message' => $message]);
    }

    /** Give an approved activity to a class, or to one reading group of it, from the class's own page. */
    public function assignToClass(Request $request, SchoolClass $class): JsonResponse
    {
        $validated = $request->validate([
            'activity_id' => ['required', 'integer'],
            'reading_band' => ['nullable', Rule::in(array_keys(ReadingLevel::GROUPS))],
        ], ['activity_id.required' => 'Choose an activity to assign.']);

        $done = $this->activities->assignToClass($request->user()->teacher, $class, (int) $validated['activity_id'], $validated['reading_band'] ?? null);

        if ($done['blocked'] !== null) {
            return response()->json(['message' => $done['blocked'], 'errors' => ['activity_id' => [$done['blocked']]]], 422);
        }

        return response()->json(['message' => $done['message']]);
    }

    public function share(Request $request, Activity $activity): JsonResponse
    {
        $this->mine($request, $activity);
        abort_if($activity->status !== 'Approved', 403, 'Only an Approved activity can be shared.');
        abort_if($activity->shared_to_repository, 403, 'This activity has already been shared.');

        $message = $this->activities->share($request->user()->teacher, $activity, $request->validate($this->activities->shareRules()));

        return response()->json(['message' => $message, 'credits' => (int) $request->user()->teacher->fresh()->free_generation_credits_remaining]);
    }

    // ------------------------------------------------------------------ asking for new activities

    /** What the Generate screen needs: the skills and types, each level's word range, how many the teacher has, their classes and credits. */
    public function generateOptions(Request $request): JsonResponse
    {
        $teacher = $request->user()->teacher;
        $activities = Activity::where('created_by_teacher_id', $teacher->id)->get(['id', 'status', 'grade_level', 'activity_type', 'difficulty_tier']);

        $have = [];
        foreach ($activities->whereIn('status', ['Approved', 'Draft']) as $a) {
            $slot = &$have[$a->gradeNumber()][$a->activity_type][$a->difficulty_tier];
            $slot ??= [0, 0];
            $slot[$a->status === 'Approved' ? 0 : 1]++;
            unset($slot);
        }

        return response()->json([
            'credits' => (int) $teacher->free_generation_credits_remaining,
            'locked' => $teacher->status !== 'Active',
            'grades' => $teacher->gradesAllowed(),
            'competencies' => collect(config('activity_competencies.competencies'))->map(fn ($c, $key) => ['key' => $key, 'label' => $c['label'], 'description' => $c['description'], 'types' => $c['activity_types']])->values(),
            'typeLabels' => config('activity_competencies.activity_type_labels'),
            'bands' => config('activity_levels.bands'),
            'levelInfo' => config('activity_levels.info'),
            'maxPerLevel' => (int) config('activity_levels.max_per_level'),
            'have' => $have,
            'baseSeconds' => ActivityGeneration::BASE_SECONDS,
            'extraSeconds' => ActivityGeneration::EXTRA_SECONDS_PER_TEXT,
            'classes' => SchoolClass::where('teacher_id', $teacher->id)->orderBy('name')->get()->reject(fn ($c) => SchoolClass::isYearPast($c->school_year))
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'grade' => $c->grade_level])->values(),
            'generation' => ($notice = ActivityGeneration::noticeFor($teacher->id)) ? $notice->toStatus() : null,
        ]);
    }

    /** Ask for new activities. Written in the background: this answers at once and the list's `generation` follows it. */
    public function generate(Request $request): JsonResponse
    {
        $generation = $this->activities->startGeneration($request->user()->teacher, $request->all());

        // If the queue runs inline the result is already known; otherwise it is Queued and the app follows it.
        if ($generation->status === ActivityGeneration::FAILED) {
            $generation->update(['acknowledged_at' => now()]);

            return response()->json(['message' => $generation->message, 'errors' => ['generate' => [$generation->message]]], 422);
        }

        return response()->json(['generation' => $generation->toStatus()], 202);
    }

    // ------------------------------------------------------------------ helpers

    private function mine(Request $request, Activity $activity): void
    {
        abort_if($activity->created_by_teacher_id !== $request->user()->teacher->id, 404);
    }

    /** What a list row (and the top of one activity) says. */
    private function row(Activity $a, bool $withPassage = false): array
    {
        $assigned = $a->relationLoaded('assignments') ? $a->assignments->count() : $a->assignments()->count();

        return [
            'id' => $a->id,
            'title' => $a->title,
            'grade' => $a->grade_level,
            'type' => $a->typeLabel(),
            'level' => $a->difficulty_tier,
            'aiLevel' => $a->ai_difficulty_tier,
            'moved' => $a->movedByTeacher(),
            'words' => $a->word_count,
            'status' => $a->status,
            'shared' => (bool) $a->shared_to_repository,
            'assigned' => $assigned,
            'why' => $a->whyLevel(),
            'passage' => $withPassage ? $a->passage_text : null,
        ];
    }

    private function assignmentLabel($as): string
    {
        return match (true) {
            $as->learner !== null => trim($as->learner->first_name.' '.$as->learner->last_name),
            $as->schoolClass !== null && $as->reading_band !== null => ($as->schoolClass->name).' ('.strtolower(ReadingLevel::GROUPS[$as->reading_band]['title'] ?? $as->reading_band).' group)',
            $as->schoolClass !== null => $as->schoolClass->name,
            $as->group_tag !== null => 'the '.$as->group_tag.' group',
            default => 'Someone',
        };
    }
}
