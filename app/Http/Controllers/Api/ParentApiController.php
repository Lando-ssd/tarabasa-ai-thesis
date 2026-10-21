<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Learner;
use App\Models\Notification;
use App\Models\OpenRepositoryListing;
use App\Models\ParentAccount;
use App\Models\ReadingSession;
use App\Services\DataErasureService;
use App\Services\LearnerPinService;
use App\Services\ParentChildService;
use App\Services\ParentRepositoryService;
use App\Support\ChildSummary;
use App\Support\LearnerClock;
use App\Support\LearnerQr;
use App\Support\ParentInsights;
use App\Support\ReadingLevel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * The phone app's Parent screens: Home, Children (and adding or linking one), Progress, Activities (the Open Repository) and Alerts.
 * Thin JSON wrappers over the same classes the website's pages call (ChildSummary, ParentInsights, ParentChildService,
 * ParentRepositoryService), so what a parent reads about their child is the same words and numbers on the phone and on the website.
 *
 * A Parent only ever reaches the children linked to their own account. Anything else answers 404.
 */
class ParentApiController extends Controller
{
    /** The most readings and alerts one answer carries. A family's history is short; this only guards the size of the answer. */
    private const LIMIT = 40;

    public function __construct(
        private readonly ParentChildService $children,
        private readonly ParentRepositoryService $repository,
        private readonly LearnerPinService $pins,
    ) {}

    // ------------------------------------------------------------------ home

    /** The children (with a flag for one whose latest reading needs a look), and one child's week: summary, readings and who started them. */
    public function home(Request $request): JsonResponse
    {
        $parent = $this->parent($request);
        $learners = $this->learners($parent);
        $flagged = $this->flaggedByLearner($learners);
        $selected = $this->pick($learners, $request);

        $child = null;
        if ($selected) {
            $recent = ReadingSession::where('learner_id', $selected->id)
                ->where('session_type', '!=', 'Diagnostic')
                ->with('activity')
                ->orderByDesc('timestamp')
                ->take(3)
                ->get();

            $child = $this->childRow($selected, $flagged[$selected->id] ?? false) + [
                'points' => (int) $selected->points,
                'wcpm' => ($recent->first()?->wcpm !== null) ? (int) round($recent->first()->wcpm) : null,
                'summary' => ChildSummary::for($selected),
                'sources' => $this->sources($selected),
                'recent' => $recent->map(fn (ReadingSession $s) => $this->sessionRow($s))->values(),
            ];
        }

        return response()->json([
            'children' => $learners->map(fn (Learner $l) => $this->childRow($l, $flagged[$l->id] ?? false))->values(),
            'selected' => $selected?->id,
            'child' => $child,
            'unread' => $this->unread($request),
        ]);
    }

    // ------------------------------------------------------------------ children

    public function children(Request $request): JsonResponse
    {
        $parent = $this->parent($request);
        $learners = $this->learners($parent);
        $flagged = $this->flaggedByLearner($learners);

        return response()->json([
            'children' => $learners->map(fn (Learner $l) => $this->childRow($l, $flagged[$l->id] ?? false) + [
                'dayStreak' => $l->readingDayStreak(),
                'relationship' => $l->pivot->relationship,
                'creator' => (bool) $l->pivot->is_creator,
                // Removing a child who has another guardian only unlinks this parent (the app says so before it asks for the password).
                'otherGuardians' => max(0, $l->parents()->count() - 1),
            ])->values(),
        ]);
    }

    /** Every list the Add a child questions are chosen from, with the website's own wording. */
    public function childOptions(): JsonResponse
    {
        return response()->json($this->children->options());
    }

    public function storeChild(Request $request): JsonResponse
    {
        // A phone sends the character, not a photo: photos need a place to live that survives a restart, which the server does not have yet.
        $rules = $this->children->createRules((bool) config('legal.api_consent_required'));
        unset($rules['avatar_photo'], $rules['photo_consent']);

        $validated = $request->validate($rules, $this->children->createMessages());
        $learner = $this->children->create($request->user(), $validated, null, 'app');

        return response()->json(['child' => $this->childRow($learner->load('schoolClass'), false), 'message' => "{$learner->first_name} was added."], 201);
    }

    /** A second guardian joins a child with the code and the child's PIN. Refusals come back under `learner_code`, in the website's words. */
    public function linkChild(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'learner_code' => ['required', 'string', 'max:20'],
            'pin' => ['required', 'digits:4'],
            'relationship' => ['required', Rule::in(['Mother', 'Father', 'Guardian'])],
            'guardian_confirm' => config('legal.api_consent_required') ? ['accepted'] : ['nullable', 'boolean'],
        ], [
            ...\App\Services\ConsentService::messages(),
            'pin.required' => "Type the child's 4 digit PIN.",
            'pin.digits' => 'The PIN is 4 digits.',
        ]);

        $learner = $this->children->link($request->user(), $validated['learner_code'], $validated['pin'], $validated['relationship'], $validated['guardian_confirm'] ?? false, 'app');

        return response()->json(['child' => $this->childRow($learner->load('schoolClass'), false), 'message' => "You're now linked to {$learner->first_name} as a guardian."]);
    }

    /** The child's card: name, Learner Code and the QR code (the code only, never the PIN), drawn here so no outside service sees it. */
    public function card(Request $request, Learner $learner): JsonResponse
    {
        $this->own($request, $learner);

        return response()->json([
            'name' => trim($learner->first_name.' '.$learner->last_name),
            'firstName' => $learner->first_name,
            'code' => $learner->learner_code,
            'qrSvg' => LearnerQr::svg($learner->learner_code, 240),
        ]);
    }

    /**
     * A guardian makes a new PIN for a child who forgot theirs (LearnerPinService holds every rule). `mode` is `generate` (the system makes
     * one, returned ONCE in `pin`) or `choose` (the guardian types it, so nothing comes back). A refusal is a 422 under `password` or `pin`.
     */
    public function resetPin(Request $request, Learner $learner): JsonResponse
    {
        $validated = $request->validate(LearnerPinService::formRules(), LearnerPinService::formMessages());

        $result = $this->pins->reset($request->user(), $learner, $validated['password'], $validated['mode'], $validated['pin'] ?? null);

        return response()->json($result);
    }

    /**
     * A guardian removes a child's profile (DataErasureService holds every rule: a linked guardian only, their password again, and with
     * another guardian linked it only unlinks this one). A refusal is a 422 under `password`.
     */
    public function removeChild(Request $request, Learner $learner, DataErasureService $erasure): JsonResponse
    {
        $data = $request->validate(['password' => ['required', 'string']], ['password.required' => 'Type your password, so we know it is you.']);

        return response()->json($erasure->removeChild($request->user(), $learner, $data['password']));
    }

    // ------------------------------------------------------------------ progress

    /** One child's progress: level in plain words, what to practise at home, the week, the accuracy of recent readings and the history. */
    public function progress(Request $request): JsonResponse
    {
        $parent = $this->parent($request);
        $learners = $this->learners($parent);
        $flagged = $this->flaggedByLearner($learners);
        $selected = $this->pick($learners, $request);

        $child = null;
        if ($selected) {
            $sessions = ReadingSession::where('learner_id', $selected->id)
                ->where('session_type', '!=', 'Diagnostic')
                ->with('activity')
                ->orderByDesc('timestamp')
                ->get();

            $level = ParentInsights::level($selected);

            // array_merge, not +, so the longer `level` (with the plain sentence) replaces the short one childRow() carries.
            $child = array_merge($this->childRow($selected, $flagged[$selected->id] ?? false), [
                'level' => ['band' => $level['band'], 'label' => $level['label'], 'short' => $level['short'], 'step' => $level['step'], 'plain' => $level['plain']],
                'home' => ParentInsights::homePractice($selected),
                'summary' => ChildSummary::for($selected),
                'sources' => $this->sources($selected),
                'readings' => $sessions->count(),
                // The accuracy of the last readings, oldest first, for the small chart.
                'trend' => $sessions->take(20)->reverse()->values()->map(fn (ReadingSession $s) => $s->accuracy_percent !== null ? (int) round($s->accuracy_percent) : null)->all(),
                'history' => $sessions->take(self::LIMIT)->map(fn (ReadingSession $s) => $this->sessionRow($s))->values(),
            ]);
        }

        return response()->json([
            'children' => $learners->map(fn (Learner $l) => $this->childRow($l, $flagged[$l->id] ?? false))->values(),
            'selected' => $selected?->id,
            'child' => $child,
        ]);
    }

    // ------------------------------------------------------------------ activities (the Open Repository)

    /** What other teachers shared, best match for this child first, with the child's unlock status and the parent's own rating. */
    public function repository(Request $request): JsonResponse
    {
        $parent = $this->parent($request);
        $learners = $this->learners($parent);
        $selected = $this->pick($learners, $request);

        $filter = in_array($request->query('filter'), ['free', 'paid', 'unlocked'], true) ? $request->query('filter') : 'all';
        $onlyBest = $request->query('best') === '1';

        $found = $this->repository->rows($parent, $selected, $filter, $onlyBest);

        return response()->json([
            'children' => $learners->map(fn (Learner $l) => $this->childRow($l, false))->values(),
            'selected' => $selected?->id,
            'filter' => $filter,
            'best' => $onlyBest,
            'hasMatching' => $found['hasMatching'],
            'listings' => $found['rows']->take(self::LIMIT)->map(fn (array $row) => $this->listingRow($row))->values(),
        ]);
    }

    /** Unlock for one child. Free at once; a paid one is confirmed on the phone first (the payment is simulated, nothing is charged). */
    public function unlock(Request $request, OpenRepositoryListing $listing): JsonResponse
    {
        $parent = $this->parent($request);
        $learner = $this->linked($request, $parent, $request->validate(['learner_id' => ['required', 'integer']])['learner_id']);

        $this->repository->unlock($parent, $listing, $learner);

        return response()->json(['message' => "Unlocked \"{$listing->activity->title}\" for {$learner->first_name}."]);
    }

    public function rate(Request $request, OpenRepositoryListing $listing): JsonResponse
    {
        $parent = $this->parent($request);
        $validated = $request->validate([
            'learner_id' => ['required', 'integer'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);
        $learner = $this->linked($request, $parent, $validated['learner_id']);

        $this->repository->rate($parent, $listing, $learner, (int) $validated['rating'], $validated['comment'] ?? null);

        return response()->json(['message' => 'Thanks for rating "'.$listing->activity->title.'"!']);
    }

    // ------------------------------------------------------------------ alerts

    /** The parent's notifications, newest first, with the day they belong to (the family's own days, not the server's). */
    public function alerts(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $filter = in_array($request->query('filter'), ['attention', 'routine'], true) ? $request->query('filter') : 'all';

        $rows = Notification::where('recipient_user_id', $userId)
            ->when($filter === 'attention', fn ($q) => $q->whereIn('type', [Notification::TYPE_NEEDS_ATTENTION, Notification::TYPE_PIN_LOCKED]))
            ->when($filter === 'routine', fn ($q) => $q->whereIn('type', [Notification::TYPE_SESSION_SUMMARY, Notification::TYPE_LEVEL_CONFIRMED, Notification::TYPE_GUARDIAN_LINKED, Notification::TYPE_CLASS_JOINED, Notification::TYPE_PIN_CHANGED, Notification::TYPE_GUARDIAN_REMOVED, Notification::TYPE_LEARNER_REMOVED]))
            ->with('learner')
            ->orderByDesc('timestamp')
            ->orderByDesc('id')
            ->take(self::LIMIT)
            ->get();

        return response()->json([
            'filter' => $filter,
            'unread' => $this->unread($request),
            'alerts' => $rows->map(fn (Notification $n) => [
                'id' => $n->id,
                'type' => $n->type,
                'attention' => in_array($n->type, [Notification::TYPE_NEEDS_ATTENTION, Notification::TYPE_PIN_LOCKED], true),
                'message' => $n->message,
                'read' => (bool) $n->is_read,
                'childId' => $n->learner_id,
                'child' => $n->learner?->first_name,
                'day' => LearnerClock::dayLabel($n->timestamp),
                'at' => $n->timestamp->toIso8601String(),
            ])->values(),
        ]);
    }

    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        abort_unless($notification->recipient_user_id === $request->user()->id, 404);

        $notification->update(['is_read' => true]);

        return response()->json(['unread' => $this->unread($request)]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        Notification::where('recipient_user_id', $request->user()->id)->where('is_read', false)->update(['is_read' => true]);

        return response()->json(['unread' => 0]);
    }

    // ------------------------------------------------------------------ helpers

    private function parent(Request $request): ParentAccount
    {
        $parent = $request->user()->parentProfile;
        abort_if($parent === null, 403, 'This account has no parent profile.');

        return $parent;
    }

    /** @return Collection<int, Learner> */
    private function learners(ParentAccount $parent): Collection
    {
        return $parent->learners()->with('schoolClass')->orderBy('first_name')->get();
    }

    /** The child asked for, or the first. A child that is not this parent's own falls back to the first, never an error. */
    private function pick(Collection $learners, Request $request): ?Learner
    {
        if ($learners->isEmpty()) {
            return null;
        }

        return $learners->firstWhere('id', (int) $request->query('learner_id', $learners->first()->id)) ?? $learners->first();
    }

    private function own(Request $request, Learner $learner): void
    {
        $linked = $this->parent($request)->learners()->whereKey($learner->id)->exists();
        abort_unless($linked, 404);
    }

    private function linked(Request $request, ParentAccount $parent, mixed $learnerId): Learner
    {
        $learner = $parent->learners()->find($learnerId);
        abort_unless($learner, 403, 'That child is not linked to your account.');

        return $learner;
    }

    private function unread(Request $request): int
    {
        return Notification::where('recipient_user_id', $request->user()->id)->where('is_read', false)->count();
    }

    /** Whether each child's latest real reading was flagged for extra support. */
    private function flaggedByLearner(Collection $learners): array
    {
        return ReadingSession::whereIn('learner_id', $learners->pluck('id'))
            ->where('session_type', '!=', 'Diagnostic')
            ->orderByDesc('timestamp')
            ->get()
            ->groupBy('learner_id')
            ->map(fn ($sessions) => (bool) $sessions->first()->flagged_needs_attention)
            ->all();
    }

    private function childRow(Learner $l, bool $flagged): array
    {
        $level = ReadingLevel::forAdult($l);

        return [
            'id' => $l->id,
            'firstName' => $l->first_name,
            'name' => trim($l->first_name.' '.$l->last_name),
            'grade' => $l->grade_level,
            'className' => $l->schoolClass?->name,
            'code' => $l->learner_code,
            'avatar' => $l->avatar_id,
            'flagged' => $flagged,
            'level' => ['band' => $level['band'], 'label' => $level['label'], 'short' => $level['short'], 'step' => $level['step']],
        ];
    }

    private function sources(Learner $learner): array
    {
        $s = ReadingSession::sourceSummaryForLearner($learner->id);

        return [
            'teacher' => ['count' => $s['Teacher']['count'], 'average' => $s['Teacher']['avg_accuracy'] !== null ? (int) $s['Teacher']['avg_accuracy'] : null],
            'parent' => ['count' => $s['Parent']['count'], 'average' => $s['Parent']['avg_accuracy'] !== null ? (int) $s['Parent']['avg_accuracy'] : null],
        ];
    }

    private function sessionRow(ReadingSession $s): array
    {
        return [
            'id' => $s->id,
            'title' => $s->activity->title ?? 'Reading activity',
            'source' => $s->initiated_by,
            'accuracy' => $s->accuracy_percent !== null ? (int) round($s->accuracy_percent) : null,
            'wcpm' => $s->wcpm !== null ? (int) round($s->wcpm) : null,
            'flagged' => (bool) $s->flagged_needs_attention,
            'moved' => $s->level_before !== null && $s->level_after !== null && $s->level_before !== $s->level_after ? $s->level_after : null,
            'day' => LearnerClock::dayLabel($s->timestamp),
            'at' => $s->timestamp->toIso8601String(),
        ];
    }

    /** @param  array<string, mixed>  $row */
    private function listingRow(array $row): array
    {
        /** @var OpenRepositoryListing $listing */
        $listing = $row['listing'];
        $a = $listing->activity;
        $by = $listing->teacher?->user;

        return [
            'id' => $listing->id,
            'title' => $a->title,
            'grade' => $a->grade_level,
            'skill' => $a->competency_label,
            'level' => $a->difficulty_tier,
            'words' => $a->word_count,
            'teacher' => $by ? trim($by->first_name.' '.$by->last_name) : null,
            'paid' => $listing->price_type === 'Paid',
            'price' => $listing->price_type === 'Paid' ? '₱'.number_format($listing->price) : 'Free',
            'unlocked' => (bool) $row['is_unlocked'],
            'stars' => $row['avg_rating'],
            'ratings' => $row['rating_count'],
            'mine' => $row['my_rating'] ? ['rating' => (int) $row['my_rating']->rating, 'comment' => $row['my_rating']->comment] : null,
            'matchesLevel' => (bool) $row['level_match'],
            'matchesPattern' => $row['pattern_match'],
        ];
    }
}
