<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Learner;
use App\Models\ReadingSession;
use App\Support\ActivityFit;
use App\Support\ReadingLevel;
use Illuminate\Support\Collection;

/**
 * Decides, after each scored practice reading, whether a child moves one step on the reading ladder (config/diagnostic.php,
 * rungs 0 to 6) and how far they are from the next step. The rules and the reasons for them are in config/progression.php.
 *
 * In short: UP needs several strong readings (90 percent or more) of real length, of different activities, after the
 * child's last move, and moves them ONE step; DOWN needs two weak readings in a row (under 70 percent) of texts that were
 * not too long for them. A single reading, however perfect or poor, never moves a child by itself.
 *
 * The three stored levels (Beginning, Developing, Proficient) stay what they were; they follow the step
 * (ReadingLevel::TIER_RUNGS), so a step inside a level (Word Builder to Sentence Reader both sit in Beginning/Developing
 * at the edge) moves the level only when it crosses the edge.
 */
class ReadingProgression
{
    /** The stored level a rung belongs to. */
    public static function levelForRung(int $rung): string
    {
        foreach (ReadingLevel::TIER_RUNGS as $level => [$low, $high]) {
            if ($rung >= $low && $rung <= $high) {
                return $level;
            }
        }

        return 'Beginning';
    }

    /** Where the child is on the ladder now. */
    public function currentRung(Learner $learner): int
    {
        return ReadingLevel::readiness($learner)['rung'] ?? 2;
    }

    /**
     * The readings that count toward the next move, newest first: practice readings after the last move.
     *
     * @return Collection<int, ReadingSession>
     */
    private function evidence(Learner $learner, int $limit): Collection
    {
        return ReadingSession::where('learner_id', $learner->id)
            ->where('session_type', 'Practice')
            ->whereNotNull('accuracy_percent')
            ->when($learner->rung_changed_at, fn ($q) => $q->where('timestamp', '>', $learner->rung_changed_at))
            ->with('activity')
            ->orderByDesc('timestamp')->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /** Is this reading long enough, and strong enough, to count toward leaving the step $rung? */
    private function qualifies(float $accuracy, ?Activity $activity, int $rung): bool
    {
        $min = config("progression.min_words.{$rung}");

        return $min !== null
            && $accuracy >= (float) config('progression.up_accuracy')
            && $activity !== null
            && (int) $activity->word_count >= (int) $min;
    }

    /**
     * What one NEW (not yet saved) reading does.
     *
     * @return array{rungBefore:int, rungAfter:int, moved:?string, levelBefore:string, levelAfter:string, have:int, need:int, stepBefore:string, stepAfter:string, nextStep:?string, stepChanged:bool, rungLabelAfter:string}
     */
    public function evaluate(Learner $learner, Activity $activity, float $accuracy): array
    {
        $rung = $this->currentRung($learner);
        $window = (int) config('progression.window');
        $earlier = $this->evidence($learner, $window - 1);

        // newest first: the new reading, then the ones before it
        $readings = collect([['accuracy' => $accuracy, 'activity' => $activity]])
            ->merge($earlier->map(fn (ReadingSession $s) => ['accuracy' => (float) $s->accuracy_percent, 'activity' => $s->activity]));

        $qualifying = $readings->filter(fn (array $r) => $this->qualifies($r['accuracy'], $r['activity'], $rung));
        $moved = null;
        $rungAfter = $rung;

        $needUp = (int) config('progression.up_readings');
        $top = (int) config('progression.top_rung');

        if ($rung < $top
            && $this->qualifies($accuracy, $activity, $rung)
            && $qualifying->count() >= $needUp
            && $qualifying->pluck('activity.id')->unique()->count() >= (int) config('progression.up_distinct_activities')) {
            $moved = 'up';
            $rungAfter = $rung + 1;
        } elseif ($rung > 0 && $accuracy < (float) config('progression.down_accuracy')) {
            // Weak readings only count against the child when the text was a fair one for them. A text far too long
            // for their step failing says nothing new about the step.
            $fair = fn (array $r) => $r['activity'] === null || ActivityFit::forLearner($r['activity'], $learner)['verdict'] !== ActivityFit::BLOCKED;
            $needDown = (int) config('progression.down_readings');
            $recent = $readings->filter($fair)->take($needDown);

            if ($fair(['activity' => $activity]) && $recent->count() >= $needDown && $recent->every(fn (array $r) => $r['accuracy'] < (float) config('progression.down_accuracy'))) {
                $moved = 'down';
                $rungAfter = $rung - 1;
            }
        }

        $steps = ReadingLevel::STEPS;
        $levelBefore = $learner->mastery_level ?? self::levelForRung($rung);

        return [
            'rungBefore' => $rung,
            'rungAfter' => $rungAfter,
            'moved' => $moved,
            'levelBefore' => $levelBefore,
            'levelAfter' => $moved ? self::levelForRung($rungAfter) : $levelBefore,
            // after a move the count starts again; otherwise it is the strong readings so far
            'have' => $moved ? 0 : min($qualifying->count(), $needUp),
            'need' => $needUp,
            'stepBefore' => $steps[ReadingLevel::stepForRung($rung)]['name'],
            'stepAfter' => $steps[ReadingLevel::stepForRung($rungAfter)]['name'],
            'nextStep' => $rungAfter < $top ? $steps[ReadingLevel::stepForRung($rungAfter + 1)]['name'] : null,
            'stepChanged' => ReadingLevel::stepForRung($rungAfter) !== ReadingLevel::stepForRung($rung),
            'rungLabelAfter' => ReadingLevel::RUNG_LABELS[$rungAfter],
        ];
    }

    /**
     * Where the child stands toward the next step, for the teacher and parent screens (nothing is changed).
     *
     * @return array{rung:int, step:string, rungLabel:string, nextRungLabel:?string, have:int, need:int, atTop:bool}
     */
    public function progress(Learner $learner): array
    {
        $rung = $this->currentRung($learner);
        $needUp = (int) config('progression.up_readings');
        $top = (int) config('progression.top_rung');
        $have = $this->evidence($learner, (int) config('progression.window'))
            ->filter(fn (ReadingSession $s) => $this->qualifies((float) $s->accuracy_percent, $s->activity, $rung))
            ->count();
        $steps = ReadingLevel::STEPS;

        return [
            'rung' => $rung,
            'step' => $steps[ReadingLevel::stepForRung($rung)]['name'],
            'rungLabel' => ReadingLevel::RUNG_LABELS[$rung],
            'nextRungLabel' => $rung < $top ? ReadingLevel::RUNG_LABELS[$rung + 1] : null,
            'have' => min($have, $needUp),
            'need' => $needUp,
            'atTop' => $rung >= $top,
        ];
    }
}
