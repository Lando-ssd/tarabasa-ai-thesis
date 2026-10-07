<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivityAssignment;
use App\Models\Learner;
use App\Models\ReadingSession;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\TeacherAlertAction;
use App\Support\ActivityFit;
use App\Support\ErrorPatterns;
use Illuminate\Validation\ValidationException;
use App\Support\LearnerClock;
use App\Support\ReadingLevel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Who needs a teacher's attention, why, and what to do about it. Worked out from the readings each
 * time (nothing is stored but "handled"), for the learners in the Teacher's classes of the school
 * year in progress.
 *
 * Three kinds, each with the evidence behind it and one suggested step taken from the Teacher's
 * OWN approved activities (never anything outside what they approved):
 *  - support: the last three readings were all under 70 percent, OR the latest reading was flagged for extra support
 *    (under 70 percent, the same line the app flags a reading at and tells the Teacher about at once), the latest within
 *    two weeks. A learner with a single 25 percent reading is not made to wait for two more bad ones;
 *  - up: the last three readings were all 90 percent or above, the latest within two weeks (the
 *    same 90 percent the app itself uses to move a reader up a step), so the strong readers are
 *    seen too and not left in a beginner group;
 *  - quiet: a child who has read before and has not read for a week or more.
 * A routine session summary is not an alert; those stay on the Alerts page as a collapsed list.
 */
class TeacherAlerts
{
    public const SUPPORT_BELOW = 70;

    public const UP_AT = 90;

    public const IN_A_ROW = 3;

    public const FRESH_DAYS = 14;

    public const QUIET_DAYS = 7;

    public function __construct(private ActivitySuggestions $suggestions)
    {
    }

    /**
     * The number of alerts waiting, for the menu bar. Cached for a minute so it costs one
     * calculation per minute per Teacher rather than one per page.
     */
    public function openCount(Teacher $teacher): int
    {
        return Cache::remember(self::cacheKey($teacher), 60, fn () => $this->forTeacher($teacher, withSuggestions: false)->count());
    }

    public static function cacheKey(Teacher $teacher): string
    {
        return "teacher-alerts:{$teacher->id}";
    }

    /**
     * @return Collection<int, array<string, mixed>> open alerts, support first, then up, then quiet
     */
    public function forTeacher(Teacher $teacher, bool $withSuggestions = true): Collection
    {
        $classes = SchoolClass::where('teacher_id', $teacher->id)
            ->where('school_year', SchoolClass::currentSchoolYear())
            ->with('learners')
            ->get();

        $learners = $classes->flatMap(fn (SchoolClass $c) => $c->learners->each->setRelation('schoolClass', $c))->unique('id')->values();
        if ($learners->isEmpty()) {
            return collect();
        }

        $ids = $learners->pluck('id');

        // The recent readings of everyone, newest first, in one query.
        $recent = ReadingSession::whereIn('learner_id', $ids)
            ->with('activity:id,title')
            ->where('session_type', '!=', 'Diagnostic')
            ->where('timestamp', '>=', now()->subDays(60))
            ->orderByDesc('timestamp')->orderByDesc('id')
            ->get()
            ->groupBy('learner_id');

        // When each child last read at all, however long ago (for the quiet ones).
        $lastRead = ReadingSession::whereIn('learner_id', $ids)
            ->where('session_type', '!=', 'Diagnostic')
            ->toBase()
            ->select('learner_id', DB::raw('MAX(timestamp) as last_at'))
            ->groupBy('learner_id')
            ->pluck('last_at', 'learner_id');

        $handled = TeacherAlertAction::where('teacher_id', $teacher->id)->get()->keyBy(fn ($a) => $a->learner_id.'|'.$a->kind);

        $alerts = collect();

        foreach ($learners as $learner) {
            $sessions = $recent->get($learner->id, collect());
            $last3 = $sessions->take(self::IN_A_ROW);

            $alert = $this->supportAlert($learner, $last3) ?? $this->upAlert($learner, $last3);
            if ($alert) {
                $alerts->push($alert + ['sessions' => $sessions]);
            }

            if ($quiet = $this->quietAlert($learner, $lastRead[$learner->id] ?? null)) {
                $alerts->push($quiet + ['sessions' => $sessions]);
            }
        }

        $alerts = $alerts->reject(function (array $a) use ($handled) {
            $row = $handled->get($a['learner']->id.'|'.$a['kind']);

            return $row && $row->handled_at->greaterThanOrEqualTo($a['evidence_at']);
        });

        if ($withSuggestions) {
            $alerts = $alerts->map(fn (array $a) => $this->withSuggestion($teacher, $a));
        }

        $order = ['support' => 0, 'up' => 1, 'quiet' => 2];

        return $alerts->sortBy(fn (array $a) => [$order[$a['kind']], -$a['evidence_at']->timestamp])->values();
    }

    /** @param  Collection<int, ReadingSession>  $last3 newest first */
    private function supportAlert(Learner $learner, Collection $last3): ?array
    {
        $latest = $last3->first();

        if ($latest === null || $this->at($latest)->lt(now()->subDays(self::FRESH_DAYS))) {
            return null;
        }

        $threeLow = $last3->count() >= self::IN_A_ROW && ! $last3->contains(fn ($s) => (float) $s->accuracy_percent >= self::SUPPORT_BELOW);

        // The app itself flags a reading under 70 percent for extra support and tells the Teacher at once (the Home
        // banner). The Alerts page must agree: a child whose latest reading was flagged needs attention even with
        // only one reading, instead of being invisible until two more bad ones arrive.
        $latestFlagged = $latest->accuracy_percent !== null && ((bool) $latest->flagged_needs_attention || (float) $latest->accuracy_percent < self::SUPPORT_BELOW);

        if (! $threeLow && ! $latestFlagged) {
            return null;
        }

        if (! $threeLow) {
            return $this->flaggedReadingAlert($learner, $latest);
        }

        $newest = $this->at($last3->first());

        $oldest = $this->at($last3->last());
        $days = max(1, (int) ceil($oldest->diffInHours($newest) / 24));
        $accuracies = $last3->reverse()->map(fn ($s) => (int) round($s->accuracy_percent))->values();
        $profile = ErrorPatterns::profile($last3);

        return [
            'kind' => 'support',
            'learner' => $learner,
            'class' => $learner->schoolClass,
            'title' => $learner->first_name.' '.$learner->last_name.' needs support',
            'band' => ReadingLevel::band($learner),
            'evidence' => self::IN_A_ROW.' readings under '.self::SUPPORT_BELOW.'% in '.$days.' '.($days === 1 ? 'day' : 'days'),
            'why' => $this->missedLine($learner, $last3).' Accuracy has been '.$this->list($accuracies->map(fn ($v) => $v.'%')->all()).'.',
            'pattern' => $this->patternLine($profile),
            'profile' => $profile,
            'evidence_at' => $newest,
        ];
    }

    /** The latest reading alone was flagged for extra support. */
    private function flaggedReadingAlert(Learner $learner, ReadingSession $reading): array
    {
        $accuracy = (int) round($reading->accuracy_percent);
        $title = $reading->activity?->title;
        $single = collect([$reading]);
        $profile = ErrorPatterns::profile($single);

        return [
            'kind' => 'support',
            'learner' => $learner,
            'class' => $learner->schoolClass,
            'title' => $learner->first_name.' '.$learner->last_name.' needs support',
            'band' => ReadingLevel::band($learner),
            'evidence' => 'Latest reading '.$accuracy.'%, under '.self::SUPPORT_BELOW.'%',
            'why' => $learner->first_name.' scored '.$accuracy.'%'.($title ? ' on "'.$title.'"' : '').' and the app flagged it for extra support. '.$this->missedLine($learner, $single),
            'pattern' => $this->patternLine($profile),
            'profile' => $profile,
            'evidence_at' => $this->at($reading),
        ];
    }

    /** @param  Collection<int, ReadingSession>  $last3 newest first */
    private function upAlert(Learner $learner, Collection $last3): ?array
    {
        if ($last3->count() < self::IN_A_ROW || $last3->contains(fn ($s) => (float) $s->accuracy_percent < self::UP_AT)) {
            return null;
        }

        $newest = $this->at($last3->first());
        if ($newest->lt(now()->subDays(self::FRESH_DAYS))) {
            return null;
        }

        $accuracies = $last3->reverse()->map(fn ($s) => (int) round($s->accuracy_percent))->values();

        return [
            'kind' => 'up',
            'learner' => $learner,
            'class' => $learner->schoolClass,
            'title' => $learner->first_name.' '.$learner->last_name.' is ready to move up',
            'band' => ReadingLevel::band($learner),
            'evidence' => self::IN_A_ROW.' readings at '.self::UP_AT.'% or above',
            'why' => $learner->first_name."'s last three readings scored ".$this->list($accuracies->map(fn ($v) => $v.'%')->all()).'. The app moves a reader up one step after repeated readings at '.self::UP_AT.'% or above.',
            'pattern' => null,
            'profile' => null,
            'evidence_at' => $newest,
        ];
    }

    private function quietAlert(Learner $learner, ?string $lastAt): ?array
    {
        if ($lastAt === null) {
            return null;
        }

        $last = Carbon::parse($lastAt);
        if ($last->gt(now()->subDays(self::QUIET_DAYS))) {
            return null;
        }

        $days = (int) floor($last->diffInHours(now()) / 24);

        return [
            'kind' => 'quiet',
            'learner' => $learner,
            'class' => $learner->schoolClass,
            'title' => $learner->first_name.' '.$learner->last_name.' has not read in '.$days.' days',
            'band' => ReadingLevel::band($learner),
            'evidence' => $days.' days without a reading',
            'why' => 'The last reading was on '.LearnerClock::local($last)->format('M j').'.',
            'pattern' => null,
            'profile' => null,
            'evidence_at' => $last,
        ];
    }

    /**
     * The suggested step: one of the Teacher's own approved activities, picked for the level and,
     * for a child who needs support, for the kind of mistake they keep making.
     */
    private function withSuggestion(Teacher $teacher, array $alert): array
    {
        $alert['suggestion'] = null;

        if ($alert['kind'] === 'quiet') {
            return $alert;
        }

        $learner = $alert['learner'];
        $group = ReadingLevel::groupOf($learner);

        if ($alert['kind'] === 'support') {
            $tier = 'Easy';
            $main = ErrorPatterns::mainPattern($alert['profile']);
            $card = $this->suggestions->forLearner($teacher, $learner, $tier, $alert['profile']);
            $tip = $main ? ErrorPatterns::CATEGORIES[$main]['tip'] : 'Read it together one to one for five minutes.';
        } else {
            // One step above where they read now; a child with no level yet is offered Medium.
            $tier = match ($group) {
                'instructional', 'independent' => 'Hard',
                default => 'Medium',
            };
            $card = $this->suggestions->forLearner($teacher, $learner, $tier);
            $tip = null;
        }

        $alert['suggestion'] = ['tier' => $tier, 'card' => $card, 'tip' => $tip];

        return $alert;
    }

    // -------------------------------------------------------------------- acting on an alert

    public function markHandled(Teacher $teacher, Learner $learner, string $kind): void
    {
        TeacherAlertAction::updateOrCreate(
            ['teacher_id' => $teacher->id, 'learner_id' => $learner->id, 'kind' => $kind],
            ['handled_at' => now()]
        );

        Cache::forget(self::cacheKey($teacher));
    }

    /**
     * Gives the suggested activity to this one learner and marks the alert handled. Nothing is
     * trusted from the form: the learner must be in one of the Teacher's classes of this school
     * year, and the activity must be the Teacher's own and approved.
     */
    public function assign(Teacher $teacher, Learner $learner, Activity $activity, string $kind): ActivityAssignment
    {
        // An activity far too long for this child cannot be given from an alert either (see ActivityFit).
        $fit = ActivityFit::forLearner($activity, $learner);

        if ($fit['verdict'] === ActivityFit::BLOCKED) {
            throw ValidationException::withMessages(['activity_id' => $fit['note']]);
        }

        $assignment = ActivityAssignment::firstOrCreate([
            'activity_id' => $activity->id,
            'learner_id' => $learner->id,
            'assigned_by_teacher_id' => $teacher->id,
        ]);

        $this->markHandled($teacher, $learner, $kind);

        return $assignment;
    }

    // -------------------------------------------------------------------- wording helpers

    private function at(ReadingSession $session): Carbon
    {
        return Carbon::parse($session->timestamp);
    }

    /** "Jun missed 'hen', 'cow' and 'said' in every reading." */
    private function missedLine(Learner $learner, Collection $sessions): string
    {
        $perSession = $sessions->map(function ($s) {
            return collect((array) ($s->word_feedback ?? []))
                ->filter(fn ($w) => ErrorPatterns::classify((array) $w) !== null)
                ->pluck('reference')->filter()->map(fn ($w) => strtolower($w))->unique()->values();
        });

        $counts = $perSession->flatten()->countBy()->sortDesc();
        $repeated = $counts->filter(fn ($n) => $n >= 2)->keys()->take(3)->all();

        if ($repeated !== []) {
            $every = $counts->filter(fn ($n) => $n >= $sessions->count())->keys()->intersect($repeated)->count() === count($repeated);

            return $learner->first_name.' missed '.$this->list(array_map(fn ($w) => "'{$w}'", $repeated)).($every ? ' in every reading.' : ' in more than one reading.');
        }

        $some = $counts->keys()->take(3)->all();

        return $some === []
            ? $learner->first_name.($sessions->count() === 1 ? ' scored low on this reading.' : ' scored low on all '.$this->list([$sessions->count()]).' readings.')
            : $learner->first_name."'s most missed words were ".$this->list(array_map(fn ($w) => "'{$w}'", $some)).'.';
    }

    private function patternLine(array $profile): ?string
    {
        $main = ErrorPatterns::mainPattern($profile);
        if ($main === null) {
            return null;
        }

        $top = $profile['top'][0];
        $line = 'Mostly '.ErrorPatterns::CATEGORIES[$main]['label'].' ('.$top['share'].'% of the misses'.($top['examples'] ? ', for example '.implode(', ', array_slice($top['examples'], 0, 2)) : '').').';

        return $profile['early'] ? $line.' This is early: it is based on '.$profile['readings'].' readings.' : $line;
    }

    /** "a", "a and b", "a, b and c" */
    private function list(array $items): string
    {
        $items = array_values($items);
        if (count($items) <= 1) {
            return (string) ($items[0] ?? '');
        }

        return implode(', ', array_slice($items, 0, -1)).' and '.end($items);
    }
}
