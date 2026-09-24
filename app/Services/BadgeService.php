<?php

namespace App\Services;

use App\Models\GamePlay;
use App\Models\Learner;
use App\Models\LearnerBadge;
use App\Models\PromotionRecord;
use App\Models\ReadingSession;
use App\Support\LearnerClock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Badges. The 100 definitions live in config/badges.php; this service works
 * out which of them a learner has earned, and when, from their real reading
 * history, and records each one once in `learner_badges`.
 *
 * Every rule looks at history rather than at "what just happened", so the
 * same call is correct after a reading, after the diagnostic, and when a
 * learner who read before a badge existed opens their badge page: they get
 * the badges they had already earned, dated when they earned them, without
 * anyone having to remember to award them.
 *
 * A badge counts as "new" (worth celebrating on the results screen) only when
 * it was earned by the reading that just finished. Ones found in older history
 * are recorded quietly.
 */
class BadgeService
{
    private const LEVELS = ['Beginning', 'Developing', 'Proficient'];

    /** A badge earned within this many minutes is treated as earned by what just happened. */
    private const RECENT_MINUTES = 15;

    public function checkAfterPracticeReading(Learner $learner, bool $leveledUp = false): array
    {
        return $this->sync($learner);
    }

    public function checkAfterDiagnosticFinish(Learner $learner, bool $isFirstEverReading = false): array
    {
        return $this->sync($learner);
    }

    /**
     * Records every badge the learner has now earned and returns the ones
     * earned by the reading that just finished, ready for the celebration
     * screen: [['code','name','description','emoji', ...], ...].
     */
    public function sync(Learner $learner): array
    {
        $evaluation = $this->evaluate($learner);
        $existing = LearnerBadge::where('learner_id', $learner->id)->pluck('badge_code')->all();
        $newlyEarned = [];

        // Two passes so "Super Reader" (earn 50 badges) sees the others first.
        foreach ([false, true] as $countRules) {
            foreach (config('badges.badges') as $code => $definition) {
                $isCount = ($definition['rule']['type'] ?? null) === 'badge_count';
                if ($isCount !== $countRules) {
                    continue;
                }

                if ($isCount) {
                    $evaluation[$code] = $this->badgeCount($definition['rule'], count($existing));
                }

                $result = $evaluation[$code] ?? null;

                if (! $result || $result['at'] === null || in_array($code, $existing, true)) {
                    continue;
                }

                LearnerBadge::create([
                    'learner_id' => $learner->id,
                    'badge_code' => $code,
                    'earned_at' => $result['at'],
                ]);
                $existing[] = $code;

                if ($result['at']->gte(now()->subMinutes(self::RECENT_MINUTES))) {
                    $newlyEarned[] = array_merge(['code' => $code], $definition);
                }
            }
        }

        // A learner catching up on many badges at once would otherwise get a wall of them.
        return array_slice($newlyEarned, 0, 4);
    }

    /**
     * Every defined badge with the learner's real state: earned or not, the
     * date, and (for count-style badges) how far along they are.
     *
     * @return list<array<string, mixed>>
     */
    public function summaryFor(Learner $learner): array
    {
        $this->sync($learner);

        $evaluation = $this->evaluate($learner);
        $earned = LearnerBadge::where('learner_id', $learner->id)->get()->keyBy('badge_code');
        $earnedCount = $earned->count();

        return collect(config('badges.badges'))->map(function (array $definition, string $code) use ($evaluation, $earned, $earnedCount) {
            $row = $earned->get($code);
            $result = $evaluation[$code] ?? null;

            if (($definition['rule']['type'] ?? null) === 'badge_count') {
                $result = ['at' => null, 'current' => $earnedCount, 'target' => $definition['rule']['target']];
            }

            return array_merge($definition, [
                'code' => $code,
                'earned' => $row !== null,
                'earnedAt' => $row?->earned_at,
                'comingSoon' => $definition['rule'] === null,
                'current' => $result['current'] ?? null,
                'target' => $result['target'] ?? null,
            ]);
        })->values()->all();
    }

    /**
     * @return array<string, array{at: ?Carbon, current: int|float|null, target: int|float|null}>
     */
    private function evaluate(Learner $learner): array
    {
        $all = ReadingSession::where('learner_id', $learner->id)->orderBy('timestamp')->orderBy('id')->get();
        $practice = $all->where('session_type', 'Practice')->values();

        $ctx = [
            'learner' => $learner,
            'games' => GamePlay::where('learner_id', $learner->id)->orderBy('played_at')->orderBy('id')->get(),
            'all' => $all,
            'practice' => $practice,
            'local' => $practice->map(fn (ReadingSession $s) => LearnerClock::local($s->timestamp))->all(),
            'days' => $practice->map(fn (ReadingSession $s) => LearnerClock::local($s->timestamp)->toDateString())->unique()->values(),
        ];

        $results = [];

        foreach (config('badges.badges') as $code => $definition) {
            $rule = $definition['rule'];

            if ($rule === null || $rule['type'] === 'badge_count') {
                continue;
            }

            $results[$code] = $this->{'rule'.str_replace(' ', '', ucwords(str_replace('_', ' ', $rule['type'])))}($rule, $ctx);
        }

        return $results;
    }

    private function res(?Carbon $at, int|float|null $current = null, int|float|null $target = null): array
    {
        return ['at' => $at, 'current' => $current, 'target' => $target];
    }

    private function badgeCount(array $rule, int $have): array
    {
        return $this->res($have >= $rule['target'] ? now() : null, $have, $rule['target']);
    }

    /** The end of a local calendar day (Y-m-d), stored in UTC, but never later than now. */
    private function dayEnd(string $day): Carbon
    {
        $end = Carbon::parse($day, LearnerClock::timezone())->endOfDay()->setTimezone(config('app.timezone'));

        return $end->gt(now()) ? now() : $end;
    }

    private function at(ReadingSession $session): Carbon
    {
        return Carbon::parse($session->timestamp);
    }

    // ------------------------------------------------------------------ rules

    private function ruleFirstReading(array $rule, array $ctx): array
    {
        $first = $ctx['all']->first();

        return $this->res($first ? $this->at($first) : null);
    }

    private function ruleReadings(array $rule, array $ctx): array
    {
        $count = $ctx['practice']->count();
        $nth = $count >= $rule['target'] ? $ctx['practice'][$rule['target'] - 1] : null;

        return $this->res($nth ? $this->at($nth) : null, min($count, $rule['target']), $rule['target']);
    }

    private function ruleFirstBook(array $rule, array $ctx): array
    {
        $first = $ctx['practice']->first();

        return $this->res($first ? $this->at($first) : null);
    }

    /** Longest run of consecutive calendar days with a reading. */
    private function ruleDayStreak(array $rule, array $ctx): array
    {
        $days = $ctx['days']->sort()->values();
        $run = 0;
        $best = 0;
        $reachedOn = null;
        $previous = null;

        foreach ($days as $day) {
            $date = Carbon::parse($day);
            $run = ($previous && $previous->copy()->addDay()->isSameDay($date)) ? $run + 1 : 1;
            $previous = $date;
            $best = max($best, $run);

            if ($run >= $rule['target'] && $reachedOn === null) {
                $reachedOn = $this->dayEnd($day);
            }
        }

        return $this->res($reachedOn, min($best, $rule['target']), $rule['target']);
    }

    private function ruleAccuracy(array $rule, array $ctx): array
    {
        $needed = $rule['count'] ?? 1;
        $consecutive = $rule['consecutive'] ?? false;
        $qualifying = 0;
        $run = 0;
        $best = 0;
        $reachedAt = null;

        foreach ($ctx['practice'] as $session) {
            $passes = (float) $session->accuracy_percent >= $rule['min'];
            $run = $passes ? $run + 1 : 0;
            $qualifying += $passes ? 1 : 0;
            $progress = $consecutive ? $run : $qualifying;
            $best = max($best, $progress);

            if ($progress >= $needed && $reachedAt === null) {
                $reachedAt = $this->at($session);
            }
        }

        return $this->res($reachedAt, min($best, $needed), $needed > 1 ? $needed : null);
    }

    private function ruleWcpm(array $rule, array $ctx): array
    {
        $hit = $ctx['practice']->first(fn (ReadingSession $s) => $s->wcpm !== null && (float) $s->wcpm >= $rule['min']);
        $best = (float) $ctx['practice']->max('wcpm');

        return $this->res($hit ? $this->at($hit) : null, (int) min($best, $rule['min']), $rule['min']);
    }

    private function rulePoints(array $rule, array $ctx): array
    {
        $points = (int) $ctx['learner']->points;

        return $this->res($points >= $rule['target'] ? now() : null, min($points, $rule['target']), $rule['target']);
    }

    private function levelIndex(?string $level): int
    {
        $index = array_search($level, self::LEVELS, true);

        return $index === false ? -1 : $index;
    }

    private function ruleLevelReached(array $rule, array $ctx): array
    {
        $wanted = $this->levelIndex($rule['level']);

        $hit = $ctx['all']->first(fn (ReadingSession $s) => $this->levelIndex($s->level_after) >= $wanted);

        if ($hit) {
            return $this->res($this->at($hit));
        }

        return $this->res($this->levelIndex($ctx['learner']->mastery_level) >= $wanted ? now() : null);
    }

    private function ruleLevelUps(array $rule, array $ctx): array
    {
        $ups = $ctx['practice']->filter(fn (ReadingSession $s) => $this->levelIndex($s->level_after) > $this->levelIndex($s->level_before))->values();
        $nth = $ups->count() >= $rule['target'] ? $ups[$rule['target'] - 1] : null;

        return $this->res($nth ? $this->at($nth) : null, min($ups->count(), $rule['target']), $rule['target'] > 1 ? $rule['target'] : null);
    }

    private function ruleComeback(array $rule, array $ctx): array
    {
        $dipped = false;

        foreach ($ctx['practice'] as $session) {
            $before = $this->levelIndex($session->level_before);
            $after = $this->levelIndex($session->level_after);

            if ($after < $before) {
                $dipped = true;
            } elseif ($after > $before && $dipped) {
                return $this->res($this->at($session));
            }
        }

        return $this->res(null);
    }

    private function ruleHoldLevel(array $rule, array $ctx): array
    {
        $run = 0;
        $best = 0;
        $reachedAt = null;

        foreach ($ctx['practice'] as $session) {
            $run = $session->level_after === $rule['level'] ? $run + 1 : 0;
            $best = max($best, $run);

            if ($run >= $rule['target'] && $reachedAt === null) {
                $reachedAt = $this->at($session);
            }
        }

        return $this->res($reachedAt, min($best, $rule['target']), $rule['target']);
    }

    private function rulePromoted(array $rule, array $ctx): array
    {
        $record = PromotionRecord::where('learner_id', $ctx['learner']->id)->whereNotNull('claimed_at')->orderBy('claimed_at')->first();

        return $this->res($record ? Carbon::parse($record->claimed_at) : null);
    }

    private function ruleSkills(array $rule, array $ctx): array
    {
        $learner = $ctx['learner'];
        $skills = $learner->practiceableSubdomains();

        if ($learner->subdomain_states === null || $skills === []) {
            return $this->res(null, 0, count($skills));
        }

        $reached = collect($skills)->filter(fn (string $name) => ($learner->subdomain_states[$name]['proficiency'] ?? -1) >= $rule['min'])->count();

        return $this->res($reached === count($skills) ? now() : null, $reached, count($skills));
    }

    /**
     * Practice sessions grouped by the Monday of their week, in the child's own timezone.
     *
     * @return Collection<string, Collection<int, array{s: ReadingSession, t: Carbon}>>
     */
    private function weeks(array $ctx): Collection
    {
        return $ctx['practice']
            ->map(fn (ReadingSession $s, int $i) => ['s' => $s, 't' => $ctx['local'][$i]])
            ->groupBy(fn (array $row) => $row['t']->copy()->startOfWeek()->toDateString());
    }

    /** The session that made the count in a week reach $target, or null. */
    private function nthInWeek(Collection $week, int $target): ?ReadingSession
    {
        return $week->count() >= $target ? $week->values()[$target - 1]['s'] : null;
    }

    private function ruleGoalWeeks(array $rule, array $ctx): array
    {
        $goal = (int) config('reading_goals.weekly_target');
        $metWeeks = $this->weeks($ctx)
            ->map(fn (Collection $week) => $this->nthInWeek($week, $goal))
            ->filter()
            ->sortKeys();

        $run = 0;
        $best = 0;
        $reachedAt = null;
        $previous = null;

        foreach ($metWeeks as $monday => $session) {
            $date = Carbon::parse($monday);
            $run = ($previous && $previous->copy()->addWeek()->isSameDay($date)) ? $run + 1 : 1;
            $previous = $date;
            $best = max($best, $run);

            if ($run >= $rule['target'] && $reachedAt === null) {
                $reachedAt = $this->at($session);
            }
        }

        return $this->res($reachedAt, min($best, $rule['target']), $rule['target'] > 1 ? $rule['target'] : null);
    }

    private function ruleGoalByWednesday(array $rule, array $ctx): array
    {
        $goal = (int) config('reading_goals.weekly_target');

        foreach ($this->weeks($ctx) as $week) {
            $early = $week->filter(fn (array $row) => $row['t']->dayOfWeekIso <= 3)->values();
            $session = $early->count() >= $goal ? $early[$goal - 1]['s'] : null;

            if ($session) {
                return $this->res($this->at($session));
            }
        }

        return $this->res(null);
    }

    private function ruleWeekCount(array $rule, array $ctx): array
    {
        $best = 0;

        foreach ($this->weeks($ctx) as $week) {
            $best = max($best, $week->count());
            $session = $this->nthInWeek($week, $rule['target']);

            if ($session) {
                return $this->res($this->at($session), $rule['target'], $rule['target']);
            }
        }

        return $this->res(null, min($best, $rule['target']), $rule['target']);
    }

    private function ruleWeekAllDays(array $rule, array $ctx): array
    {
        foreach ($this->weeks($ctx) as $week) {
            $seen = [];

            foreach ($week as $row) {
                $seen[$row['t']->dayOfWeekIso] = true;

                if (count($seen) === 7) {
                    return $this->res($this->at($row['s']));
                }
            }
        }

        return $this->res(null);
    }

    private function ruleWeekendBoth(array $rule, array $ctx): array
    {
        foreach ($this->weeks($ctx) as $week) {
            $seen = [];

            foreach ($week as $row) {
                if (in_array($row['t']->dayOfWeekIso, [6, 7], true)) {
                    $seen[$row['t']->dayOfWeekIso] = true;
                }

                if (count($seen) === 2) {
                    return $this->res($this->at($row['s']));
                }
            }
        }

        return $this->res(null);
    }

    private function ruleGoalInMonth(array $rule, array $ctx): array
    {
        $goal = (int) config('reading_goals.weekly_target');
        $byMonth = [];

        foreach ($this->weeks($ctx) as $monday => $week) {
            $session = $this->nthInWeek($week, $goal);

            if ($session) {
                $byMonth[substr($monday, 0, 7)][] = $session;
            }
        }

        $best = 0;

        foreach ($byMonth as $sessions) {
            $best = max($best, count($sessions));

            if (count($sessions) >= $rule['times']) {
                return $this->res($this->at($sessions[$rule['times'] - 1]), $rule['times'], $rule['times']);
            }
        }

        return $this->res(null, min($best, $rule['times']), $rule['times']);
    }

    private function ruleDistinctBooks(array $rule, array $ctx): array
    {
        $seen = [];

        foreach ($ctx['practice'] as $session) {
            $seen[$session->activity_id] = true;

            if (count($seen) >= $rule['target']) {
                return $this->res($this->at($session), $rule['target'], $rule['target']);
            }
        }

        return $this->res(null, count($seen), $rule['target']);
    }

    private function ruleSameBook(array $rule, array $ctx): array
    {
        $counts = [];
        $best = 0;

        foreach ($ctx['practice'] as $session) {
            $counts[$session->activity_id] = ($counts[$session->activity_id] ?? 0) + 1;
            $best = max($best, $counts[$session->activity_id]);

            if ($counts[$session->activity_id] >= $rule['target']) {
                return $this->res($this->at($session), $rule['target'], $rule['target']);
            }
        }

        return $this->res(null, $best, $rule['target']);
    }

    private function rulePersonalBest(array $rule, array $ctx): array
    {
        $bests = [];

        foreach ($ctx['practice'] as $session) {
            $previous = $bests[$session->activity_id] ?? null;

            if ($previous !== null && (float) $session->accuracy_percent > $previous) {
                return $this->res($this->at($session));
            }

            $bests[$session->activity_id] = max($previous ?? 0, (float) $session->accuracy_percent);
        }

        return $this->res(null);
    }

    private function rulePaysOff(array $rule, array $ctx): array
    {
        $bests = [];

        foreach ($ctx['practice'] as $session) {
            $previous = $bests[$session->activity_id] ?? null;

            if ($previous !== null && (float) $session->accuracy_percent >= $previous + $rule['gain']) {
                return $this->res($this->at($session));
            }

            $bests[$session->activity_id] = max($previous ?? 0, (float) $session->accuracy_percent);
        }

        return $this->res(null);
    }

    private function ruleDiagnosticDone(array $rule, array $ctx): array
    {
        $diagnostic = $ctx['all']->where('session_type', 'Diagnostic')->last();

        if (! $diagnostic || ! app(LearnerDiagnosticService::class)->hasGenuinelyCompletedDiagnostic($ctx['learner'])) {
            return $this->res(null);
        }

        return $this->res($this->at($diagnostic));
    }

    private function ruleHour(array $rule, array $ctx): array
    {
        foreach ($ctx['practice'] as $i => $session) {
            $hour = $ctx['local'][$i]->hour;

            if ((isset($rule['before']) && $hour < $rule['before']) || (isset($rule['after']) && $hour >= $rule['after'])) {
                return $this->res($this->at($session));
            }
        }

        return $this->res(null);
    }

    private function ruleQuizPerfect(array $rule, array $ctx): array
    {
        $perfect = $ctx['practice']->filter(fn (ReadingSession $s) => $s->comprehension_score !== null && (float) $s->comprehension_score >= 100)->values();
        $nth = $perfect->count() >= $rule['target'] ? $perfect[$rule['target'] - 1] : null;

        return $this->res($nth ? $this->at($nth) : null, min($perfect->count(), $rule['target']), $rule['target'] > 1 ? $rule['target'] : null);
    }

    private function ruleExtraPractice(array $rule, array $ctx): array
    {
        $hit = $ctx['practice']->first(fn (ReadingSession $s) => $s->initiated_by === 'Parent');

        return $this->res($hit ? $this->at($hit) : null);
    }

    private function ruleTeamwork(array $rule, array $ctx): array
    {
        foreach ($this->weeks($ctx) as $week) {
            $teacher = $week->first(fn (array $row) => $row['s']->initiated_by === 'Teacher');
            $parent = $week->first(fn (array $row) => $row['s']->initiated_by === 'Parent');

            if ($teacher && $parent) {
                $later = $teacher['t']->gte($parent['t']) ? $teacher['s'] : $parent['s'];

                return $this->res($this->at($later));
            }
        }

        return $this->res(null);
    }

    private function ruleDaysRead(array $rule, array $ctx): array
    {
        $count = $ctx['days']->count();
        $reachedOn = $count >= $rule['target']
            ? $this->dayEnd($ctx['days']->sort()->values()[$rule['target'] - 1])
            : null;

        return $this->res($reachedOn, min($count, $rule['target']), $rule['target']);
    }

    private function ruleWelcomeBack(array $rule, array $ctx): array
    {
        $days = $ctx['days']->sort()->values();

        for ($i = 1; $i < $days->count(); $i++) {
            if (Carbon::parse($days[$i - 1])->diffInDays(Carbon::parse($days[$i])) >= 7) {
                return $this->res($this->dayEnd($days[$i]));
            }
        }

        return $this->res(null);
    }

    // ------------------------------------------------------------ games rules
    // Practice Games report a finished session to game_plays (GameController::finish).

    private function playsOf(array $rule, array $ctx): Collection
    {
        return isset($rule['game']) ? $ctx['games']->where('game', $rule['game'])->values() : $ctx['games']->values();
    }

    private function playedAt(GamePlay $play): Carbon
    {
        return Carbon::parse($play->played_at);
    }

    private function ruleGamePlays(array $rule, array $ctx): array
    {
        $plays = $this->playsOf($rule, $ctx);
        $nth = $plays->count() >= $rule['target'] ? $plays[$rule['target'] - 1] : null;

        return $this->res($nth ? $this->playedAt($nth) : null, min($plays->count(), $rule['target']), $rule['target'] > 1 ? $rule['target'] : null);
    }

    /** A session that ended on at least this level (climbing to it counts, even if the session ended right then). */
    private function ruleGameLevel(array $rule, array $ctx): array
    {
        $hit = $this->playsOf($rule, $ctx)->first(fn (GamePlay $p) => $p->level_reached >= $rule['min']);

        return $this->res($hit ? $this->playedAt($hit) : null);
    }

    /** A whole round played and finished at level 3 (for Letter Match: every letter of the alphabet matched). */
    private function ruleGameTopCleared(array $rule, array $ctx): array
    {
        $hit = $this->playsOf($rule, $ctx)->first(fn (GamePlay $p) => $p->top_level_cleared);

        return $this->res($hit ? $this->playedAt($hit) : null);
    }

    private function ruleGamePerfect(array $rule, array $ctx): array
    {
        $hit = $this->playsOf($rule, $ctx)->first(fn (GamePlay $p) => $p->had_perfect_round);

        return $this->res($hit ? $this->playedAt($hit) : null);
    }

    /** Both games finished on the same calendar day, in the child's own timezone. */
    private function ruleGameBothSameDay(array $rule, array $ctx): array
    {
        $seen = [];

        foreach ($ctx['games'] as $play) {
            $day = LearnerClock::local($play->played_at)->toDateString();
            $seen[$day][$play->game] = true;

            if (count($seen[$day]) === 2) {
                return $this->res($this->playedAt($play));
            }
        }

        return $this->res(null);
    }
}
