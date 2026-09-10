<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The three newer, lighter Learner dashboard panels beyond Badges/
 * Bookshelf: Journey (the real winding-path visual over
 * Learner::competencyProgressSummary() — this is Sprint "Slice 4" folded
 * into the app-shell redesign, not a separate later pass), Weekly Goal
 * (a real count of this week's Practice ReadingSessions vs. a fixed
 * config target — see config/reading_goals.php), and Growth (the same
 * real sessions bucketed by weekday). Goals and Growth deliberately
 * share Learner::thisWeeksPracticeReadingSessions() as their one source
 * of "what counts as this week's reading," so they can't drift apart on
 * the week boundary or which session types count.
 */
class LearnerGrowthController extends Controller
{
    /**
     * 5 fixed points per competency row (a real zigzag, not a straight
     * bar) — the same underlying 0-100 proficiency that used to just fill
     * a plain bar now places a real "you are here" marker by linear
     * interpolation between two of these 5 points, and lights up whichever
     * checkpoints proficiency has actually passed (>= 0/25/50/75/100).
     * Nothing here is fabricated: every number comes straight from
     * competencyProgressSummary(), just laid out as a path instead of a
     * bar. A learner_code is not "in progress but no checkpoints yet" —
     * that state is only possible when proficiency is null, which is
     * rendered as a fully locked row instead of a marker at position 0
     * (0 is a real assessed score, not the same thing as unassessed).
     */
    private const TRACK_POINTS = [
        ['x' => 20, 'y' => 48],
        ['x' => 140, 'y' => 16],
        ['x' => 260, 'y' => 48],
        ['x' => 380, 'y' => 16],
        ['x' => 480, 'y' => 48],
    ];

    public function journey(Request $request): View
    {
        $learner = $request->user('learner');

        $rows = collect($learner->competencyProgressSummary())->map(function (array $item) {
            $item['track'] = $this->buildTrack($item['proficiency']);

            return $item;
        })->all();

        return view('learner.journey', [
            'learner' => $learner,
            'rows' => $rows,
            'hasAnyData' => $learner->competency_states !== null,
        ]);
    }

    public function goals(Request $request): View
    {
        $learner = $request->user('learner');
        $count = $learner->thisWeeksPracticeReadingSessions()->count();
        $target = config('reading_goals.weekly_target');

        return view('learner.goals', [
            'learner' => $learner,
            'count' => $count,
            'target' => $target,
            'percent' => $target > 0 ? min(100, round($count / $target * 100)) : 0,
            'met' => $count >= $target,
        ]);
    }

    public function growth(Request $request): View
    {
        $learner = $request->user('learner');
        $sessions = $learner->thisWeeksPracticeReadingSessions();

        // Carbon's default week start is Monday (no week_starts_at
        // override for APP_LOCALE=en) — iterate ISO weekdays 1..7 so the
        // bars always read Mon->Sun, matching the same week boundary
        // thisWeeksPracticeReadingSessions() already queried against.
        $days = collect(range(1, 7))->map(function (int $isoDay) use ($sessions) {
            $date = now()->startOfWeek()->addDays($isoDay - 1);

            return [
                'label' => $date->format('D'),
                'count' => $sessions->filter(fn ($s) => \Illuminate\Support\Carbon::parse($s->timestamp)->dayOfWeekIso === $isoDay)->count(),
                'isToday' => $date->isToday(),
            ];
        });

        return view('learner.growth', [
            'learner' => $learner,
            'days' => $days,
            'totalThisWeek' => $sessions->count(),
            'maxCount' => max(1, $days->max('count')),
        ]);
    }

    private function buildTrack(?float $proficiency): ?array
    {
        if ($proficiency === null) {
            return null;
        }

        $proficiency = max(0.0, min(100.0, $proficiency));
        $segmentIndex = min((int) floor($proficiency / 25), 3);
        $fraction = ($proficiency - $segmentIndex * 25) / 25;

        $from = self::TRACK_POINTS[$segmentIndex];
        $to = self::TRACK_POINTS[$segmentIndex + 1];

        $marker = [
            'x' => $from['x'] + $fraction * ($to['x'] - $from['x']),
            'y' => $from['y'] + $fraction * ($to['y'] - $from['y']),
        ];

        $checkpointsDone = array_map(fn (int $i) => $proficiency >= $i * 25, range(0, 4));

        // The colored "traveled" portion of the path: every fixed point
        // fully behind the marker, plus the marker's own exact
        // interpolated position — so the line ends precisely where the
        // marker sits, not snapped to the nearest checkpoint.
        $coloredPoints = array_slice(self::TRACK_POINTS, 0, $segmentIndex + 1);
        $coloredPoints[] = $marker;

        return [
            'points' => self::TRACK_POINTS,
            'marker' => $marker,
            'checkpointsDone' => $checkpointsDone,
            'coloredPolyline' => implode(' ', array_map(fn (array $p) => "{$p['x']},{$p['y']}", $coloredPoints)),
            'greyPolyline' => implode(' ', array_map(fn (array $p) => "{$p['x']},{$p['y']}", self::TRACK_POINTS)),
        ];
    }
}
