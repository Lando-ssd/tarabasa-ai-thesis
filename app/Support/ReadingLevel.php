<?php

namespace App\Support;

use App\Models\Learner;

/**
 * One reading-level language for three audiences (see the "Behind the screens" part of the
 * October 2026 revision preview):
 *
 * - The stored level stays Beginning / Developing / Proficient (mastery_level). Nothing in the
 *   database changes; the words shown are a display choice.
 * - Teachers and Parents see Phil-IRI names: Non-reader, Frustration, Instructional, Independent
 *   (DepEd Order 14, s. 2018). Non-reader is only for a child whose first check never got past
 *   letters. The word-reading bands for ONE reading are Independent 97% and above, Instructional
 *   90 to 96%, Frustration below 90%.
 * - A child sees a four step reading path that follows how the MATATAG curriculum teaches
 *   reading: letters, words, sentences, stories. It is a name for where they are, never a grade.
 *
 * The app's own adaptive steps (up at 90%, down below 70%) are a different rule from the Phil-IRI
 * bands and are left alone; the two are shown side by side, never claimed to be the same thing.
 */
class ReadingLevel
{
    public const STEPS = [
        1 => ['name' => 'Letter Explorer', 'skill' => 'Letters and Sounds', 'blurb' => 'Say the sounds of letters', 'code' => 'RL1PWS-I-1', 'codeText' => 'Grade 1 Reading and Literacy RL1PWS-I-1: produce the sound of each letter'],
        2 => ['name' => 'Word Builder', 'skill' => 'Sounding Out Words', 'blurb' => 'Sound out and read words', 'code' => 'RL1PWS-I-5', 'codeText' => 'Grade 1 Reading and Literacy RL1PWS-I-5: sound out words accurately'],
        3 => ['name' => 'Sentence Reader', 'skill' => 'Reading Sentences', 'blurb' => 'Read sentences with care', 'code' => 'RL1CAT-III-1', 'codeText' => 'Grade 1 Reading and Literacy RL1CAT-III-1: read sentences with speed, accuracy and expression'],
        4 => ['name' => 'Story Reader', 'skill' => 'Reading Stories', 'blurb' => 'Read stories and answer questions', 'code' => 'EN3CAT-I-1', 'codeText' => 'English Grade 3 EN3CAT-I-1 and EN3CAT-I-2: read with speed, accuracy and expression, and understand stories'],
    ];

    /** The three stored levels, as the ladder rungs each covers (the ladder is in config/diagnostic.php). */
    public const TIER_RUNGS = ['Beginning' => [0, 2], 'Developing' => [3, 4], 'Proficient' => [5, 6]];

    /** Bands from most to least support needed; also the order used to compare how far apart two learners are. */
    public const BAND_ORDER = ['non' => 0, 'frustration' => 1, 'instructional' => 2, 'independent' => 3];

    /** Where on the ladder (0 to 6) the child is. Children checked before the ladder existed get an estimate from their level. */
    public static function rung(Learner $learner): ?int
    {
        if ($learner->mastery_level === null) {
            return null;
        }

        if ($learner->reading_rung !== null) {
            return (int) $learner->reading_rung;
        }

        return ['Beginning' => 1, 'Developing' => 3, 'Proficient' => 5][$learner->mastery_level] ?? null;
    }

    /**
     * The rung to save when a child's stored level changes (a practice reading moved them up or
     * down a level), so the four step path keeps agreeing with the level. A child who moves up
     * lands on the lowest rung of the new level, one who moves down on the highest rung of the
     * new level; one whose level stayed the same keeps their rung. A child with no saved rung
     * (checked before the ladder existed) stays without one: their step is worked out from the
     * level, so it follows the level on its own.
     */
    public static function rungAfterLevelChange(Learner $learner, ?string $newLevel): ?int
    {
        if ($learner->reading_rung === null) {
            return null;
        }

        $range = self::TIER_RUNGS[$newLevel] ?? null;
        if ($range === null) {
            return (int) $learner->reading_rung;
        }

        return max($range[0], min($range[1], (int) $learner->reading_rung));
    }

    /** The child's step on the four step path, 1 to 4, or null before the first check. */
    public static function step(Learner $learner): ?int
    {
        $rung = self::rung($learner);

        return $rung === null ? null : self::stepForRung($rung);
    }

    /** The step (1 to 4) a ladder rung belongs to. */
    public static function stepForRung(int $rung): int
    {
        return match (true) {
            $rung <= 0 => 1,
            $rung === 1 => 2,
            $rung <= 3 => 3,
            default => 4,
        };
    }

    public static function stepNameForRung(int $rung): string
    {
        return self::STEPS[self::stepForRung($rung)]['name'];
    }

    /**
     * Where the child is for the purpose of choosing activities for them: the rung from the first reading check when
     * there is one; otherwise, for a child who has not read the check yet, the rung the Parent's own description and
     * answers put them on (the same rule the check starts from, DiagnosticPlacement::startingRung); otherwise an
     * estimate from the stored level. Null when nothing is known at all.
     *
     * @return null|array{rung:int, source:'check'|'parent'|'estimate', sourceText:string}
     */
    public static function readiness(Learner $learner): ?array
    {
        if ($learner->reading_rung !== null) {
            return ['rung' => (int) $learner->reading_rung, 'source' => 'check', 'sourceText' => 'from the first reading check'];
        }

        $checked = $learner->relationLoaded('readingSessions')
            ? $learner->readingSessions->contains('session_type', 'Diagnostic')
            : $learner->readingSessions()->where('session_type', 'Diagnostic')->exists();

        if (! $checked && ($learner->reading_stage !== null || is_array($learner->placement_answers))) {
            $rung = array_search(DiagnosticPlacement::startingRung($learner), DiagnosticPlacement::ladder(), true);

            if ($rung !== false) {
                return ['rung' => (int) $rung, 'source' => 'parent', 'sourceText' => 'from what the parent shared, no first reading check yet'];
            }
        }

        $estimate = self::rung($learner);

        return $estimate === null ? null : ['rung' => $estimate, 'source' => 'estimate', 'sourceText' => 'estimated from the reading level'];
    }

    public static function stepName(Learner $learner): string
    {
        $step = self::step($learner);

        return $step ? self::STEPS[$step]['name'] : 'New Reader';
    }

    /** non | frustration | instructional | independent | unchecked */
    public static function band(Learner $learner): string
    {
        return match ($learner->mastery_level) {
            'Beginning' => self::rung($learner) === 0 ? 'non' : 'frustration',
            'Developing' => 'instructional',
            'Proficient' => 'independent',
            default => 'unchecked',
        };
    }

    public static function bandLabel(string $band): string
    {
        return [
            'non' => 'Non-reader',
            'frustration' => 'Frustration level',
            'instructional' => 'Instructional level',
            'independent' => 'Independent level',
            'unchecked' => 'Not checked yet',
        ][$band] ?? 'Not checked yet';
    }

    /** The short name used in counts and group headings. */
    public static function bandShort(string $band): string
    {
        return [
            'non' => 'Non-reader',
            'frustration' => 'Frustration',
            'instructional' => 'Instructional',
            'independent' => 'Independent',
            'unchecked' => 'Not checked',
        ][$band] ?? 'Not checked';
    }

    public static function bandClass(string $band): string
    {
        return ['non' => 'pl-non', 'frustration' => 'pl-frus', 'instructional' => 'pl-inst', 'independent' => 'pl-ind'][$band] ?? '';
    }

    /**
     * The three reading groups a class is split into automatically (guided reading at the
     * instructional level, small groups: MATATAG Key Stage 1 guidance). Non-reader and Frustration
     * share the first group because both need the most support.
     */
    public const GROUPS = [
        'support' => ['title' => 'Needs most support', 'sub' => 'Non-reader and Frustration level'],
        'instructional' => ['title' => 'Instructional', 'sub' => 'Reads with some help'],
        'independent' => ['title' => 'Independent', 'sub' => 'Reads on their own'],
    ];

    /** support | instructional | independent, or null for a learner whose first check is not done. */
    public static function groupOf(Learner $learner): ?string
    {
        return match (self::band($learner)) {
            'non', 'frustration' => 'support',
            'instructional' => 'instructional',
            'independent' => 'independent',
            default => null,
        };
    }

    /**
     * How many learners are at each level, in the order non, frustration, instructional,
     * independent (the thin bar on a class card).
     *
     * @param  iterable<Learner>  $learners
     * @return array{0:int,1:int,2:int,3:int}
     */
    public static function mix(iterable $learners): array
    {
        $mix = [0, 0, 0, 0];

        foreach ($learners as $learner) {
            $order = self::BAND_ORDER[self::band($learner)] ?? null;
            if ($order !== null) {
                $mix[$order]++;
            }
        }

        return $mix;
    }

    /**
     * Learners split into the three reading groups, each ordered by last name.
     *
     * @param  iterable<Learner>  $learners
     * @return array<string, \Illuminate\Support\Collection<int, Learner>>
     */
    public static function groups(iterable $learners): array
    {
        $groups = ['support' => collect(), 'instructional' => collect(), 'independent' => collect()];

        foreach ($learners as $learner) {
            $key = self::groupOf($learner);
            if ($key !== null) {
                $groups[$key]->push($learner);
            }
        }

        return array_map(fn ($g) => $g->sortBy(fn ($l) => mb_strtolower($l->last_name.' '.$l->first_name))->values(), $groups);
    }

    /**
     * Learners who read far from the rest of the class (two or more levels above or below the
     * class's middle level, in a class of at least three checked learners). Only a quiet
     * suggestion for the teacher, who decides: move the learner to another class, or give higher
     * (or easier) activities.
     *
     * @param  iterable<Learner>  $learners
     * @return array<int, array{direction: string, text: string}> keyed by learner id
     */
    public static function levelChecks(iterable $learners): array
    {
        $ordered = [];
        foreach ($learners as $learner) {
            $order = self::BAND_ORDER[self::band($learner)] ?? null;
            if ($order !== null) {
                $ordered[$learner->id] = $order;
            }
        }

        if (count($ordered) < 3) {
            return [];
        }

        $sorted = array_values($ordered);
        sort($sorted);
        $median = $sorted[intdiv(count($sorted), 2)];

        $flags = [];
        foreach ($ordered as $id => $order) {
            if ($order - $median >= 2) {
                $flags[$id] = ['direction' => 'above', 'text' => 'Reads well above most of this class.'];
            } elseif ($median - $order >= 2) {
                $flags[$id] = ['direction' => 'below', 'text' => 'Reads well below most of this class.'];
            }
        }

        return $flags;
    }

    /** Teacher and Parent facing summary of a learner's level. */
    public static function forAdult(Learner $learner): array
    {
        $band = self::band($learner);
        $step = self::step($learner);
        $skill = $step ? ['Letters', 'Words', 'Sentences', 'Stories'][$step - 1] : null;

        return [
            'band' => $band,
            'label' => self::bandLabel($band),
            'short' => self::bandShort($band),
            'class' => self::bandClass($band),
            'step' => $step ? "{$skill}, step {$step} of 4" : null,
        ];
    }

    /** The Phil-IRI band of ONE reading, from its word-reading accuracy. */
    public static function bandForAccuracy(?float $accuracy): ?string
    {
        if ($accuracy === null) {
            return null;
        }

        return match (true) {
            $accuracy >= 97 => 'independent',
            $accuracy >= 90 => 'instructional',
            default => 'frustration',
        };
    }

    /** The activity level (Easy / Medium / Hard) that suits a band. */
    public static function tierForBand(string $band): string
    {
        return match ($band) {
            'independent' => 'Hard',
            'instructional' => 'Medium',
            default => 'Easy',
        };
    }

    /**
     * After the stored level moved (up or down by a reading), keep the rung inside the new level's
     * range, so the path step the child sees always agrees with the level.
     */
    public static function syncRungToTier(Learner $learner): void
    {
        $range = self::TIER_RUNGS[$learner->mastery_level] ?? null;
        $rung = self::rung($learner);

        if ($range === null || $rung === null) {
            return;
        }

        $learner->reading_rung = max($range[0], min($range[1], $rung));
    }
}
