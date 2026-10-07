<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivityAssignment;
use App\Models\Learner;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Support\ActivityFit;
use App\Support\ErrorPatterns;
use App\Support\ReadingLevel;
use Illuminate\Support\Collection;

/**
 * Automatic suggestions for a Teacher, always taken from that Teacher's OWN approved activities
 * (nothing outside what they approved is ever offered), never assigned until the Teacher presses
 * Assign. Picked by reading level and by the MATATAG skill the adaptive engine says is next, and
 * each suggestion carries a short reason and the competency code so it can be defended.
 *
 * Level to activity level: Non-reader and Frustration get Easy, Instructional gets Medium,
 * Independent gets Hard (see ReadingLevel::tierForBand). The Teacher can ignore all of it.
 */
class ActivitySuggestions
{
    public function __construct(private MatatagAlignmentResolver $alignment)
    {
    }

    private const GROUP_TIER = ['support' => 'Easy', 'instructional' => 'Medium', 'independent' => 'Hard'];

    /** The Teacher's approved activities, newest first. Cached for the request. */
    private ?Collection $approved = null;

    private function approved(Teacher $teacher): Collection
    {
        return $this->approved ??= Activity::where('created_by_teacher_id', $teacher->id)
            ->where('status', 'Approved')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Activity ids this class has already been given for this reading group: to the whole class,
     * to that group, or through a focus group the class carries.
     */
    private function alreadyGiven(SchoolClass $class, ?string $group): Collection
    {
        return ActivityAssignment::query()
            ->where(function ($q) use ($class, $group) {
                $q->where(function ($c) use ($class, $group) {
                    $c->where('class_id', $class->id)
                        ->where(fn ($b) => $b->whereNull('reading_band')->when($group, fn ($x) => $x->orWhere('reading_band', $group)));
                });
                if ($class->group_tag) {
                    $q->orWhere(fn ($g) => $g->where('group_tag', $class->group_tag)->where('assigned_by_teacher_id', $class->teacher_id));
                }
            })
            ->pluck('activity_id');
    }

    /** Topics a Parent said the children like, as words to look for in an activity's title and topic. */
    private const INTEREST_WORDS = [
        'animals' => ['animal', 'pet', 'dog', 'cat', 'bird', 'farm', 'fish'],
        'food' => ['food', 'fruit', 'rice', 'eat', 'cook', 'market', 'mango'],
        'family' => ['family', 'mother', 'father', 'nanay', 'tatay', 'home', 'sister', 'brother'],
        'vehicles' => ['vehicle', 'car', 'jeep', 'bus', 'boat', 'train', 'plane', 'tricycle'],
        'nature' => ['nature', 'tree', 'plant', 'river', 'rain', 'flower', 'garden', 'sea'],
    ];

    /**
     * A small bonus (at most 4) when an activity's title or topic is about something the learners like, as the
     * Parent said when adding them. It only breaks a tie between activities that already fit; it never decides
     * the level or the length.
     *
     * @param  iterable<Learner>  $learners
     */
    private function interestScore(Activity $activity, iterable $learners): float
    {
        $text = mb_strtolower($activity->title.' '.$activity->topic);
        $likes = 0;
        $total = 0;

        foreach ($learners as $learner) {
            $total++;
            foreach ((array) $learner->interests as $interest) {
                foreach (self::INTEREST_WORDS[$interest] ?? [] as $word) {
                    if (str_contains($text, $word)) {
                        $likes++;
                        continue 2;
                    }
                }
            }
        }

        return $total === 0 ? 0.0 : 4 * ($likes / $total);
    }

    /** What to show as one suggestion. */
    private function card(Activity $activity, string $why): array
    {
        $subdomain = $this->alignment->subdomainFor($activity);

        return [
            'activity' => $activity,
            'tier' => $activity->difficulty_tier,
            'code' => $this->alignment->cachedCodeFor($activity),
            'skill' => $subdomain ? (config("matatag_subdomains.labels.{$subdomain}.label") ?? $subdomain) : $activity->competency_label,
            'why' => $why,
        ];
    }

    /**
     * Suggestions for one reading group of a class: the group's level decides the activity level,
     * the skill most of the group is weakest in comes first, and what the class already has is
     * left out. Grade of the class first; another grade only when there is nothing else.
     *
     * @param  Collection<int, Learner>  $learners  the learners in that group
     * @return Collection<int, array>
     */
    public function forGroup(Teacher $teacher, SchoolClass $class, string $group, Collection $learners, int $limit = 3): Collection
    {
        $tier = self::GROUP_TIER[$group] ?? 'Medium';
        $given = $this->alreadyGiven($class, $group);

        $focus = $learners->map(fn (Learner $l) => $l->focusSubdomain())->filter()->countBy()->sortDesc()->keys()->first();

        // Only an activity every child in the group can read (see ActivityFit): the level name alone says how hard it
        // is for its grade, not whether THIS group can read it. A group with no suitable activity gets none, and the
        // screen says so, rather than being handed one that is too long.
        $pool = $this->approved($teacher)
            ->where('difficulty_tier', $tier)
            ->reject(fn (Activity $a) => $given->contains($a->id))
            ->filter(fn (Activity $a) => ActivityFit::suitsAll($a, $learners))
            ->values();

        $ranked = $pool->sortByDesc(function (Activity $a) use ($class, $focus, $learners) {
            $score = $a->grade_level === $class->grade_level ? 10 : 0;
            if ($focus && $this->alignment->subdomainFor($a) === $focus) {
                $score += 5;
            }

            return $score + 6 * ActivityFit::closeness($a, $learners) + $this->interestScore($a, $learners);
        })->take($limit)->values();

        $n = $learners->count();
        $why = $focus
            ? "Matches the skill {$n} ".($n === 1 ? 'learner here is' : 'learners here are').' working on next.'
            : 'A '.strtolower($tier).' activity for this reading group.';

        return $ranked->map(fn (Activity $a) => $this->card($a, $why))->values();
    }

    /**
     * Suggestions for a class with no reading data yet (no learners, or nobody has finished the
     * first reading check): one Easy, one Medium and one Hard activity for the class's grade,
     * preferring one whose title or skill mentions the class's focus group. They switch to
     * reading levels on their own once children join and read.
     *
     * @return Collection<int, array>
     */
    public function starterForClass(Teacher $teacher, SchoolClass $class): Collection
    {
        $given = $this->alreadyGiven($class, null);
        $focus = mb_strtolower((string) $class->group_tag);
        $out = collect();

        foreach (['Easy', 'Medium', 'Hard'] as $tier) {
            $pool = $this->approved($teacher)
                ->where('difficulty_tier', $tier)
                ->where('grade_level', $class->grade_level)
                ->reject(fn (Activity $a) => $given->contains($a->id));

            $pick = $pool->first(fn (Activity $a) => $focus !== '' && str_contains(mb_strtolower($a->title.' '.$a->competency_label.' '.$a->topic), $focus)) ?? $pool->first();

            if ($pick) {
                $out->push($this->card($pick, $class->grade_level.' starter'.($focus !== '' ? ', '.$class->group_tag.' focus' : '')));
            }
        }

        return $out;
    }

    /**
     * "Suggested for your classes this week": at most one suggestion per class (the classes that
     * need the most support first), for the school year in progress. A class with reading data
     * gets an activity for the reading group that needs it most; a class with none yet gets a
     * starter for its grade.
     *
     * @return Collection<int, array{class: SchoolClass, card: array, band: ?string, for: string}>
     */
    public function weekly(Teacher $teacher, int $limit = 3): Collection
    {
        $classes = SchoolClass::where('teacher_id', $teacher->id)
            ->where('school_year', SchoolClass::currentSchoolYear())
            ->with('learners')
            ->orderBy('name')
            ->get();

        $rows = collect();

        foreach ($classes as $class) {
            $groups = ReadingLevel::groups($class->learners);
            $total = collect($groups)->sum(fn ($g) => $g->count());

            if ($total === 0) {
                $starter = $this->starterForClass($teacher, $class)->first();
                if ($starter) {
                    $rows->push(['class' => $class, 'card' => $starter, 'band' => null, 'for' => $class->name.' · no data yet', 'need' => 9]);
                }
                continue;
            }

            // The group that needs the most: support first, then instructional, then independent,
            // among the groups that have someone in them.
            foreach (['support', 'instructional', 'independent'] as $order => $key) {
                if ($groups[$key]->isEmpty()) {
                    continue;
                }
                $card = $this->forGroup($teacher, $class, $key, $groups[$key], 1)->first();
                if ($card) {
                    $n = $groups[$key]->count();
                    $card['why'] = $key === 'support'
                        ? "{$n} of {$total} ".($total === 1 ? 'learner needs' : 'learners need').' the most support.'
                        : "{$n} of {$total} ".($total === 1 ? 'learner reads' : 'learners read').' at the '.strtolower(ReadingLevel::GROUPS[$key]['title']).' level.';
                    $rows->push(['class' => $class, 'card' => $card, 'band' => $key, 'for' => $class->name.' · '.strtolower(ReadingLevel::GROUPS[$key]['title']), 'need' => $order]);
                    break;
                }
            }
        }

        return $rows->sortBy('need')->take($limit)->values();
    }

    /**
     * One suggestion for one learner (an alert): an activity of the level that suits where they
     * are, from the Teacher's own approved set for the learner's grade, that the learner's class has
     * not been given yet. A learner who needs support gets Easy; one ready to move up gets the
     * next level above their reading group.
     */
    public function forLearner(Teacher $teacher, Learner $learner, ?string $tier = null, ?array $patterns = null): ?array
    {
        $group = ReadingLevel::groupOf($learner);
        $tier ??= self::GROUP_TIER[$group] ?? 'Easy';
        $class = $learner->schoolClass;
        $given = $class ? $this->alreadyGiven($class, $group) : collect();
        $direct = ActivityAssignment::where('learner_id', $learner->id)->pluck('activity_id');
        $focus = $learner->focusSubdomain();

        $pool = $this->approved($teacher)
            ->where('difficulty_tier', $tier)
            ->reject(fn (Activity $a) => $given->contains($a->id) || $direct->contains($a->id))
            ->filter(fn (Activity $a) => ActivityFit::forLearner($a, $learner)['verdict'] !== ActivityFit::BLOCKED)
            ->values();

        // When there is a pattern in the child's mistakes (see ErrorPatterns), the activity that
        // practises it best, and contains the most words they have actually missed, comes first.
        $pattern = $patterns ? ErrorPatterns::mainPattern($patterns) : null;
        $missed = $patterns['missedWords'] ?? [];

        $one = collect([$learner]);
        $pick = $pool->sortByDesc(function (Activity $a) use ($learner, $one, $focus, $pattern, $missed) {
            return ($a->grade_level === $learner->grade_level ? 10 : 0)
                + ($focus && $this->alignment->subdomainFor($a) === $focus ? 5 : 0)
                + 6 * ActivityFit::closeness($a, $one)
                + $this->interestScore($a, $one)
                + ($pattern !== null || $missed !== [] ? 12 * ErrorPatterns::fit($a, $pattern, $missed) : 0);
        })->first();

        if (! $pick) {
            return null;
        }

        $why = 'A '.strtolower($tier).' activity of yours that this learner has not been given.';
        if ($pattern !== null && ErrorPatterns::fit($pick, $pattern, $missed) > 0.15) {
            $why = 'Practises '.strtolower(ErrorPatterns::CATEGORIES[$pattern]['label']).', which this learner keeps missing.';
        }

        return $this->card($pick, $why);
    }
}
