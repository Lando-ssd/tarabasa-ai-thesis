<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ReadingSession;
use App\Models\SchoolClass;
use App\Models\Teacher;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * "Did it work?": did the activities written for a class (the "Write it for my class" option on Generate) read
 * better than the teacher's general activities, for the same children?
 *
 * Why this exists. The app does not have a trained model (there is no dataset of children's readings to train one on),
 * so its rules are the team's own and have to be checked against what really happens. This is that check for the one
 * place a rule feeds the activity generator: the facts about a class that are sent to it. It reads only what the app
 * already stores (the practice readings of the class's learners, and which activities were written for the class) and
 * says plainly what it can and cannot show.
 *
 * How it compares. For each child in the class it takes their average accuracy on the activities written for THIS class
 * and on the teacher's other, general activities, and looks at the difference for the children who read BOTH kinds.
 * Comparing a child with themselves removes the largest unknown (some children simply read better than others). It still
 * cannot remove the others: a text can be easier or more interesting, and the order they were read in matters. So it
 * is a signal to watch, never a proof, and it is only given once at least MIN_PAIRED children have read both kinds.
 * Before that it says how much is still missing instead of a number.
 *
 * Only practice readings count (the first reading check and free practice tries never do), only activities this teacher
 * wrote, and a reading counts for the class's learners as they are now.
 */
class ClassActivityOutcomes
{
    /** Children who must have read both kinds before a comparison is given. */
    public const MIN_PAIRED = 3;

    /** A difference smaller than this many points is called "about the same". */
    public const SAME_WITHIN = 3;

    /**
     * One entry per class that has at least one activity written for it, keyed by class id.
     *
     * @param  Collection<int, SchoolClass>  $classes
     * @return Collection<int, array<string, mixed>>
     */
    public function forClasses(Teacher $teacher, Collection $classes): Collection
    {
        if ($classes->isEmpty()) {
            return collect();
        }

        // One read for everything the teacher wrote: the id, and the class it was written for (if any).
        $mine = Activity::where('created_by_teacher_id', $teacher->id)->get(['id', 'generation_basis']);

        $out = collect();

        foreach ($classes as $class) {
            $result = $this->forClass($class, $mine);

            if ($result !== null) {
                $out->put($class->id, $result);
            }
        }

        return $out;
    }

    /**
     * @param  Collection<int, Activity>  $mine  the teacher's activities with their basis
     * @return array<string, mixed>|null null when nothing was written for this class
     */
    public function forClass(SchoolClass $class, Collection $mine): ?array
    {
        $forIds = $mine->filter(fn (Activity $a) => $this->writtenFor($a, $class))->pluck('id');

        if ($forIds->isEmpty()) {
            return null;
        }

        // General = this teacher's activities that were not written for any class.
        $generalIds = $mine->filter(fn (Activity $a) => empty($a->generation_basis))->pluck('id');

        $learnerIds = $class->learners()->pluck('id');

        $sessions = $learnerIds->isEmpty()
            ? collect()
            : ReadingSession::whereIn('learner_id', $learnerIds)
                ->where('session_type', 'Practice')
                ->whereNotNull('accuracy_percent')
                ->whereIn('activity_id', $forIds->merge($generalIds))
                ->get(['learner_id', 'activity_id', 'accuracy_percent']);

        $forSet = $forIds->flip();

        $stats = ['for' => $this->side($sessions->filter(fn ($s) => $forSet->has($s->activity_id))), 'general' => $this->side($sessions->filter(fn ($s) => ! $forSet->has($s->activity_id)))];

        // The same child on both kinds: the fairer comparison.
        $both = collect($stats['for']['perLearner'])->keys()->intersect(collect($stats['general']['perLearner'])->keys());
        $diffs = $both->map(fn ($id) => $stats['for']['perLearner'][$id] - $stats['general']['perLearner'][$id]);
        $paired = $both->count();
        $diff = $paired >= self::MIN_PAIRED ? round($diffs->avg(), 1) : null;

        $state = match (true) {
            $stats['for']['readings'] === 0 => 'not_read_yet',
            $diff === null => 'early',
            default => 'enough',
        };

        $verdict = $diff === null ? null : ($diff >= self::SAME_WITHIN ? 'higher' : ($diff <= -self::SAME_WITHIN ? 'lower' : 'similar'));

        $written = $forIds->count();
        $name = $class->name;

        [$headline, $detail] = match ($state) {
            'not_read_yet' => [
                "{$written} ".Str::plural('activity', $written)." written for {$name}, none read yet.",
                'The comparison starts once children have read them.',
            ],
            'early' => [
                'Not enough readings to compare yet.',
                $this->earlyDetail($stats, $paired),
            ],
            default => [
                $this->headline($verdict, $diff, $paired, $name),
                'The same children read both kinds, so a child is compared with themselves. It is still a signal to watch and not proof: a text can be easier or more interesting, and the order they were read in matters.',
            ],
        };

        return [
            'classId' => $class->id,
            'className' => $name,
            'written' => $written,
            'for' => $this->publicSide($stats['for']),
            'general' => $this->publicSide($stats['general']),
            'paired' => $paired,
            'difference' => $diff,
            'verdict' => $verdict,
            'state' => $state,
            'headline' => $headline,
            'detail' => $detail,
        ];
    }

    /** An activity was written for this class: by its id when it has one, else by the name saved with older ones. */
    private function writtenFor(Activity $a, SchoolClass $class): bool
    {
        $basis = (array) $a->generation_basis;

        if ($basis === []) {
            return false;
        }

        if (isset($basis['classId'])) {
            return (int) $basis['classId'] === $class->id;
        }

        return ($basis['className'] ?? null) === $class->name;
    }

    /**
     * @param  Collection<int, ReadingSession>  $sessions
     * @return array{readings:int, learners:int, avg:?int, perLearner:array<int, float>}
     */
    private function side(Collection $sessions): array
    {
        $per = $sessions->groupBy('learner_id')->map(fn ($rows) => (float) $rows->avg('accuracy_percent'));

        return [
            'readings' => $sessions->count(),
            'learners' => $per->count(),
            'avg' => $sessions->isEmpty() ? null : (int) round($sessions->avg('accuracy_percent')),
            'perLearner' => $per->all(),
        ];
    }

    /** What goes out of this class: counts and averages, never a per child list. */
    private function publicSide(array $side): array
    {
        return ['readings' => $side['readings'], 'learners' => $side['learners'], 'avg' => $side['avg']];
    }

    private function earlyDetail(array $stats, int $paired): string
    {
        $for = $stats['for'];
        $gen = $stats['general'];
        $need = self::MIN_PAIRED;

        $bits = [
            "{$for['readings']} ".Str::plural('reading', $for['readings']).' of class activities by '.$for['learners'].' '.Str::plural('child', $for['learners']),
            "{$gen['readings']} ".Str::plural('reading', $gen['readings']).' of general activities by '.$gen['learners'].' '.Str::plural('child', $gen['learners']),
        ];

        return implode(', ', $bits).". A comparison needs at least {$need} children who read both kinds, and so far {$paired} ".($paired === 1 ? 'has' : 'have').'.';
    }

    private function headline(?string $verdict, ?float $diff, int $paired, string $name): string
    {
        $points = abs((int) round($diff ?? 0));
        $children = "{$paired} children read both";

        return match ($verdict) {
            'higher' => "Children scored about {$points} points higher on activities written for {$name} than on general ones ({$children}).",
            'lower' => "Children scored about {$points} points lower on activities written for {$name} than on general ones ({$children}).",
            default => "Children scored about the same on activities written for {$name} and on general ones ({$children}).",
        };
    }
}
