<?php

namespace App\Support;

use App\Models\Learner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * When a child's reading improves enough to cross into the next reading level (for example from Frustration to
 * Instructional), the child STAYS in their class: a class is a grade, and the reading groups inside it are made from
 * each child's current level, so the child simply appears in the next group. This is how the app notices that move, so
 * the teacher can be told about it and shown it, from the readings that caused it (ReadingSession::rung_before and
 * rung_after, set by ReadingProgression). Nothing here moves a child anywhere.
 *
 * Only a move UP that changes the Phil-IRI band counts (a step inside the same level, such as Words to Short
 * sentences inside Frustration, is progress but not a change of group), and only while it is still true: a child who
 * has since slipped back below the new level is not "moved up" any more.
 */
class LevelMoves
{
    /** How long a move is shown as news. */
    public const FRESH_DAYS = 14;

    /**
     * The most recent move up into a higher reading level, or null.
     *
     * @param  Collection<int, \App\Models\ReadingSession>  $sessions  the learner's readings, any order
     * @return null|array{from:string, to:string, fromLabel:string, toLabel:string, at:Carbon, rungAfter:int}
     */
    public static function latestUp(Collection $sessions, Learner $learner, int $days = self::FRESH_DAYS): ?array
    {
        $move = $sessions
            ->filter(fn ($s) => $s->session_type === 'Practice'
                && $s->rung_before !== null && $s->rung_after !== null
                && (int) $s->rung_after > (int) $s->rung_before
                && ReadingLevel::bandForRung((int) $s->rung_after) !== ReadingLevel::bandForRung((int) $s->rung_before)
                && Carbon::parse($s->timestamp)->gte(now()->subDays($days)))
            ->sortByDesc(fn ($s) => Carbon::parse($s->timestamp)->timestamp)
            ->first();

        if ($move === null) {
            return null;
        }

        $from = ReadingLevel::bandForRung((int) $move->rung_before);
        $to = ReadingLevel::bandForRung((int) $move->rung_after);

        // Still true? Compare with where the child is now.
        $now = ReadingLevel::readiness($learner);
        if ($now !== null && ReadingLevel::BAND_ORDER[ReadingLevel::bandForRung($now['rung'])] < ReadingLevel::BAND_ORDER[$to]) {
            return null;
        }

        return [
            'from' => $from,
            'to' => $to,
            'fromLabel' => ReadingLevel::bandShort($from),
            'toLabel' => ReadingLevel::bandShort($to),
            'at' => Carbon::parse($move->timestamp),
            'rungAfter' => (int) $move->rung_after,
        ];
    }
}
