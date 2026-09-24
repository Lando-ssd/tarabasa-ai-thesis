<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateActivitiesJob;
use App\Models\Activity;
use App\Models\ActivityAssignment;
use App\Models\ActivityGeneration;
use App\Models\Learner;
use App\Models\OpenRepositoryListing;
use App\Models\SchoolClass;
use App\Services\ActivityAiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ActivityController extends Controller
{
    /** Drafts shown on the board's "To review" column at a time, and rows per page in the list. */
    private const TRAY = 4;

    private const PER_PAGE = 7;

    /** Approved cards shown in each level column before "See all". */
    private const COLUMN = 5;

    /**
     * The old Generate page: Generate now lives in a window on the Activities screen, so a
     * bookmarked link lands there with the window open.
     */
    public function create(): RedirectResponse
    {
        return redirect()->route('teacher.activities.index', ['generate' => 1]);
    }

    /**
     * Generate Activity — Teacher Actor Prompt Step 7. Deliberately has NO 'teacher.active'
     * middleware: a Pending Teacher can use their 2 free credits, per Step 3. Only "touching real
     * students" actions are gated by Active status.
     *
     * The Teacher chooses how many of EACH level (0 to 5). The request is saved and written in the
     * background (GenerateActivitiesJob), because the real gemini_activity_gen service takes a
     * minute to several minutes and always writes all three levels: too long to hold a web
     * request open. The Activities page follows its progress. Credits are checked BEFORE (Step
     * 7.1) and decremented by exactly 1 only when the activities have been saved (Step 7.6), so a
     * failed or timed-out call never costs a credit.
     */
    public function generate(Request $request): RedirectResponse
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
        $max = (int) config('activity_levels.max_per_level');

        $validated = $request->validate([
            'grade_level' => ['required', Rule::in(['Grade 1', 'Grade 2', 'Grade 3'])],
            'competency' => ['required', Rule::in(array_keys($competencies))],
            'activity_type' => ['required', 'string'],
            'levels' => ['required', 'array'],
            'levels.Easy' => ['nullable', 'integer', 'min:0', "max:{$max}"],
            'levels.Medium' => ['nullable', 'integer', 'min:0', "max:{$max}"],
            'levels.Hard' => ['nullable', 'integer', 'min:0', "max:{$max}"],
            'topic' => ['nullable', 'string', 'max:200'],
            'teacher_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $wanted = collect(config('activity_levels.tiers'))
            ->mapWithKeys(fn (string $tier) => [$tier => (int) ($validated['levels'][$tier] ?? 0)])
            ->all();

        if (array_sum($wanted) === 0) {
            throw ValidationException::withMessages(['levels' => 'Choose at least one level to generate.']);
        }

        // The API rejects an activity_type that isn't mapped to the chosen competency (422) —
        // check it here too, so the Teacher gets an immediate, specific message.
        $allowedTypes = $competencies[$validated['competency']]['activity_types'];
        if (! in_array($validated['activity_type'], $allowedTypes, true)) {
            throw ValidationException::withMessages([
                'activity_type' => 'That activity type isn\'t available for the selected skill.',
            ]);
        }

        // One at a time: a second request now could spend a credit the first one still needs.
        if (ActivityGeneration::activeFor($teacher->id)) {
            throw ValidationException::withMessages([
                'generate' => 'Your last request is still being written. It shows at the top of Activities. Wait for it to finish, then ask for more.',
            ]);
        }

        $generation = ActivityGeneration::create([
            'teacher_id' => $teacher->id,
            'status' => ActivityGeneration::QUEUED,
            'grade_level' => $validated['grade_level'],
            'competency' => $validated['competency'],
            'activity_type' => $validated['activity_type'],
            'topic' => $validated['topic'] ?? null,
            'teacher_notes' => $validated['teacher_notes'] ?? null,
            'levels' => $wanted,
        ]);

        // Normally this only queues the work and returns at once. If the queue is set to run
        // inline (QUEUE_CONNECTION=sync), it runs here and the result is known right away.
        try {
            GenerateActivitiesJob::dispatch($generation->id);
        } catch (\Throwable $e) {
            Log::error('Could not run the activity generation', ['generation' => $generation->id, 'error' => $e->getMessage()]);
        }

        $generation->refresh();

        if ($generation->status === ActivityGeneration::FAILED) {
            $generation->update(['acknowledged_at' => now()]);

            throw ValidationException::withMessages(['generate' => $generation->message]);
        }

        if ($generation->status === ActivityGeneration::DONE) {
            $generation->update(['acknowledged_at' => now()]);

            return redirect()->route('teacher.activities.index', ['view' => 'board'])->with('status', $generation->message);
        }

        $total = $generation->total();

        return redirect()
            ->route('teacher.activities.index', ['view' => 'board'])
            ->with('status', 'The AI is writing your '.$total.' '.Str::plural('activity', $total).'. You can keep working. They appear in To review when they are ready.');
    }

    /**
     * Where a "Generate activities" request has got to. The Activities page asks every few
     * seconds. If the background worker has not picked the request up (it should within seconds),
     * this page writes it itself, so a request can never sit waiting forever.
     */
    public function generationStatus(Request $request, ActivityGeneration $generation): JsonResponse
    {
        abort_if($generation->teacher_id !== $request->user()->teacher->id, 403);

        if (
            $generation->status === ActivityGeneration::QUEUED
            && $generation->created_at->lt(now()->subSeconds(ActivityGeneration::PICKUP_WAIT_SECONDS))
        ) {
            ignore_user_abort(true);
            GenerateActivitiesJob::dispatchSync($generation->id);
        }

        // A request lost for good (the server restarted while it was writing) is closed here.
        ActivityGeneration::activeFor($generation->teacher_id);

        return response()->json($generation->refresh()->toStatus());
    }

    /** The teacher has seen how a request ended: the notice on Activities can go away. */
    public function dismissGeneration(Request $request, ActivityGeneration $generation): JsonResponse
    {
        abort_if($generation->teacher_id !== $request->user()->teacher->id, 403);

        if (! $generation->isActive()) {
            $generation->update(['acknowledged_at' => now()]);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Called by the Generate window as it opens, to wake the generator while the teacher is
     * still choosing what to ask for.
     */
    public function warm(ActivityAiClient $activityAi): \Illuminate\Http\Response
    {
        $activityAi->wake();

        return response()->noContent();
    }

    /**
     * Activities — Teacher Actor Prompt Step 8. A review board (new drafts wait in "To review"
     * and are dragged into the level they belong to) and a plain list. `?fragment=1` returns
     * just the board or list, so filtering, paging and dropping a card refresh in place.
     */
    public function index(Request $request): View
    {
        $teacher = $request->user()->teacher;

        $activities = Activity::where('created_by_teacher_id', $teacher->id)
            ->with('assignments', 'repositoryListing.ratings')
            ->orderByDesc('id')
            ->get();

        $data = $this->boardData($request, $activities) + ['teacher' => $teacher];
        $data['state'] = [
            'view' => $data['view'],
            'q' => $data['q'],
            'grade' => $data['grade'],
            'status' => $data['status'] ?? 'Draft',
            'tier' => $data['tier'] ?? 'all',
            'page' => $data['page'] ?? 0,
            'tray' => $data['tray'] ?? 0,
        ];

        if ($request->boolean('fragment')) {
            return view('teacher.activities._main', $data);
        }

        return view('teacher.activities.index', $data + [
            'competencies' => config('activity_competencies.competencies'),
            'typeLabels' => config('activity_competencies.activity_type_labels'),
            'bands' => config('activity_levels.bands'),
            'levelInfo' => config('activity_levels.info'),
            'maxPerLevel' => (int) config('activity_levels.max_per_level'),
            'have' => $this->haveCounts($activities),
            'generation' => ActivityGeneration::noticeFor($teacher->id),
            // Approved activities not yet shared: what earns a teacher with no credits more.
            'shareable' => $activities->where('status', 'Approved')->where('shared_to_repository', false)->count(),
            'openGenerate' => $request->boolean('generate') || old('form') === 'generate',
        ]);
    }

    /**
     * One activity's window: the text, why it is at its level, and every action that applies
     * (approve, edit, reject or restore, move level, assign, share). Fetched when a card opens.
     */
    public function window(Request $request, Activity $activity): View
    {
        $this->authorizeOwnership($request, $activity);
        $teacher = $request->user()->teacher;

        $classes = SchoolClass::where('teacher_id', $teacher->id)->orderBy('name')->get()
            ->reject(fn ($class) => SchoolClass::isYearPast($class->school_year))
            ->values();

        return view('teacher.activities._window', [
            'activity' => $activity->load('assignments.learner', 'assignments.schoolClass', 'repositoryListing.ratings'),
            'teacher' => $teacher,
            'locked' => $teacher->status !== 'Active',
            'assignClasses' => $classes,
            'assignGroupTags' => $classes->pluck('group_tag')->filter()->unique()->values(),
            'assignLearners' => Learner::whereIn('class_id', $classes->pluck('id'))->with('schoolClass')->orderBy('first_name')->get(),
            'levelInfo' => config('activity_levels.info'),
        ]);
    }

    /**
     * Approve a Draft at the level it already has (the AI's suggestion, or the Teacher's pick).
     */
    public function approve(Request $request, Activity $activity): RedirectResponse|JsonResponse
    {
        $this->authorizeOwnership($request, $activity);
        abort_if($activity->status !== 'Draft', 403, 'Only a Draft can be approved.');

        $validated = $request->validate(['level' => ['nullable', Rule::in(config('activity_levels.tiers'))]]);

        $activity->update([
            'status' => 'Approved',
            'difficulty_tier' => $validated['level'] ?? $activity->difficulty_tier,
        ]);

        return $this->respond($request, $this->approvedMessage($activity));
    }

    /**
     * Drag and drop on the board (or the "Move to" menu): put an activity in a level, or reject
     * it. A Draft dropped in a level is approved there; an Approved one is re-leveled; only a
     * Draft can be rejected. The level the AI suggested is never overwritten, so a move is
     * always visible (and is told to the AI the next time the Teacher generates).
     */
    public function place(Request $request, Activity $activity): RedirectResponse|JsonResponse
    {
        $this->authorizeOwnership($request, $activity);

        $validated = $request->validate([
            'level' => ['required', Rule::in([...config('activity_levels.tiers'), 'reject'])],
        ]);

        if ($validated['level'] === 'reject') {
            abort_if($activity->status !== 'Draft', 403, 'Only a Draft can be rejected.');

            $activity->update(['status' => 'Rejected']);

            return $this->respond($request, "\"{$activity->title}\" rejected.", [
                'undo' => ['url' => route('teacher.activities.restore', $activity), 'label' => 'Undo'],
            ]);
        }

        abort_if($activity->status === 'Rejected', 403, 'Restore a rejected activity before placing it in a level.');

        $wasDraft = $activity->status === 'Draft';

        $activity->update(['status' => 'Approved', 'difficulty_tier' => $validated['level']]);

        return $this->respond($request, $wasDraft ? $this->approvedMessage($activity) : "Moved to {$activity->difficulty_tier}.");
    }

    /**
     * Edit a Draft's content, then approve in the SAME action — per Step 8: "Saving moves status
     * to Approved in the same action." The level is the one currently chosen.
     */
    public function update(Request $request, Activity $activity): RedirectResponse
    {
        $this->authorizeOwnership($request, $activity);
        abort_if($activity->status !== 'Draft', 403, 'Only a Draft can be edited.');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:140'],
            'instructions' => ['required', 'string', 'max:500'],
            'passage_text' => ['required', 'string', 'max:6000'],
            'level' => ['nullable', Rule::in(config('activity_levels.tiers'))],
        ]);

        $activity->update([
            'title' => $validated['title'],
            'instructions' => $validated['instructions'],
            'passage_text' => $validated['passage_text'],
            // reference_text (the text a reading is scored against) must stay in sync with
            // passage_text, or a later reading would be scored against stale, pre-edit text.
            'reference_text' => preg_replace('/\s+/', ' ', trim($validated['passage_text'])),
            'word_count' => Activity::countWords($validated['passage_text']),
            'difficulty_tier' => $validated['level'] ?? $activity->difficulty_tier,
            'status' => 'Approved',
        ]);

        return back()->with('status', "\"{$activity->title}\" updated and approved in {$activity->difficulty_tier}.");
    }

    /**
     * Reject a Draft — Step 8's third Draft action.
     */
    public function reject(Request $request, Activity $activity): RedirectResponse|JsonResponse
    {
        $this->authorizeOwnership($request, $activity);
        abort_if($activity->status !== 'Draft', 403, 'Only a Draft can be rejected.');

        $activity->update(['status' => 'Rejected']);

        return $this->respond($request, "\"{$activity->title}\" rejected.", [
            'undo' => ['url' => route('teacher.activities.restore', $activity), 'label' => 'Undo'],
        ]);
    }

    /**
     * Bring a rejected activity back to "To review" (also what Undo does after a reject).
     */
    public function restore(Request $request, Activity $activity): RedirectResponse|JsonResponse
    {
        $this->authorizeOwnership($request, $activity);
        abort_if($activity->status !== 'Rejected', 403, 'Only a rejected activity can be restored.');

        $activity->update(['status' => 'Draft']);

        return $this->respond($request, "\"{$activity->title}\" is back in To review.");
    }

    /**
     * Assign — Teacher Actor Prompt Step 8: choose exactly ONE of a specific Learner, a whole
     * Class, or a Group (group_tag shared across this Teacher's classes). Only an Approved
     * Activity can be assigned; every target ID is re-verified as belonging to this Teacher
     * server-side, never trusted just because the form only listed their own options.
     * (A Class Management window can also assign to its own class; see ClassController.)
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

        return back()->with('status', "\"{$activity->title}\" assigned.");
    }

    /**
     * Share to Repository — Teacher Actor Prompt Step 8: the second required action on an
     * Approved card, alongside Assign. Free or Paid (with a price if Paid); on success credits
     * +2 and can only ever happen once per Activity.
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

        return back()->with('status', "\"{$activity->title}\" shared to the Repository. You earned 2 free credits.");
    }

    private function authorizeOwnership(Request $request, Activity $activity): void
    {
        abort_if($activity->created_by_teacher_id !== $request->user()->teacher->id, 403);
    }

    private function approvedMessage(Activity $activity): string
    {
        return $activity->movedByTeacher()
            ? "Approved in {$activity->difficulty_tier}. The AI suggested {$activity->ai_difficulty_tier}. Your choice is saved."
            : "Approved in {$activity->difficulty_tier}.";
    }

    /** A background call (drag and drop) gets JSON; a plain form gets a redirect with a flash. */
    private function respond(Request $request, string $message, array $extra = []): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message] + $extra);
        }

        return back()->with('status', $message);
    }

    /**
     * Everything the board and the list need, worked out from the request's filters.
     *
     * @return array<string, mixed>
     */
    private function boardData(Request $request, Collection $activities): array
    {
        $view = $request->query('view') === 'list' ? 'list' : 'board';
        $query = trim((string) $request->query('q', ''));
        $grade = in_array($request->query('grade'), ['Grade 1', 'Grade 2', 'Grade 3'], true) ? $request->query('grade') : 'All';

        $matches = function (Activity $a) use ($query, $grade) {
            if ($grade !== 'All' && $a->grade_level !== $grade) {
                return false;
            }

            return $query === '' || Str::contains(
                Str::lower($a->title.' '.$a->grade_level.' '.$a->competency_label.' '.$a->typeLabel().' '.$a->topic),
                Str::lower($query),
            );
        };

        $counts = $activities->countBy('status');
        $tiers = config('activity_levels.tiers');

        $data = [
            'view' => $view,
            'q' => $query,
            'grade' => $grade,
            'counts' => ['Draft' => $counts->get('Draft', 0), 'Approved' => $counts->get('Approved', 0), 'Rejected' => $counts->get('Rejected', 0)],
            'locked' => $request->user()->teacher->status !== 'Active',
            'tiers' => $tiers,
            'levelInfo' => config('activity_levels.info'),
        ];

        if ($view === 'board') {
            $drafts = $activities->where('status', 'Draft')->filter($matches)->values();
            $pages = max(1, (int) ceil($drafts->count() / self::TRAY));
            $tray = min(max((int) $request->query('tray', 0), 0), $pages - 1);

            $data += [
                'drafts' => $drafts->slice($tray * self::TRAY, self::TRAY)->values(),
                'draftTotal' => $drafts->count(),
                'tray' => $tray,
                'trayPages' => $pages,
                'columns' => collect($tiers)->mapWithKeys(function (string $tier) use ($activities, $matches) {
                    $approved = $activities->where('status', 'Approved')->where('difficulty_tier', $tier);
                    $shown = $approved->filter($matches)->values();

                    return [$tier => [
                        'total' => $approved->count(),
                        'matching' => $shown->count(),
                        'cards' => $shown->take(self::COLUMN),
                    ]];
                })->all(),
            ];

            return $data;
        }

        $status = in_array($request->query('status'), ['Draft', 'Approved', 'Rejected'], true) ? $request->query('status') : 'Draft';
        $tier = in_array($request->query('tier'), $tiers, true) ? $request->query('tier') : 'all';

        $rows = $activities->where('status', $status)
            ->when($tier !== 'all', fn ($c) => $c->where('difficulty_tier', $tier))
            ->filter($matches)
            ->values();

        $pages = max(1, (int) ceil($rows->count() / self::PER_PAGE));
        $page = min(max((int) $request->query('page', 0), 0), $pages - 1);

        return $data + [
            'status' => $status,
            'tier' => $tier,
            'rows' => $rows->slice($page * self::PER_PAGE, self::PER_PAGE)->values(),
            'rowTotal' => $rows->count(),
            'page' => $page,
            'pages' => $pages,
            'perPage' => self::PER_PAGE,
        ];
    }

    /**
     * How many activities the Teacher already has for each grade, activity type and level, so
     * the Generate window can say "you already have 6 Hard" next to the counter.
     *
     * @return array<int, array<string, array<string, array{0:int,1:int}>>> [grade][type][tier] => [approved, drafts]
     */
    private function haveCounts(Collection $activities): array
    {
        $have = [];

        foreach ($activities as $a) {
            if (! in_array($a->status, ['Approved', 'Draft'], true)) {
                continue;
            }

            $slot = &$have[$a->gradeNumber()][$a->activity_type][$a->difficulty_tier];
            $slot ??= [0, 0];
            $slot[$a->status === 'Approved' ? 0 : 1]++;
            unset($slot);
        }

        return $have;
    }
}
