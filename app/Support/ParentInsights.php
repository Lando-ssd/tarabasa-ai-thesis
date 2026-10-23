<?php

namespace App\Support;

use App\Models\Learner;
use App\Services\LearnerDiagnosticService;

/**
 * What a parent is told about how their child is reading, in a parent's words. The website's Progress page and the phone app's both
 * call this, so the sentence a parent reads about their child's level is the same on both.
 */
class ParentInsights
{
    /**
     * Where the child is, in the names the CHILD sees on their own Home screen: Letter Explorer, Word Builder, Sentence Reader,
     * Story Reader. A parent sees the same four steps as their child; the school's Phil-IRI words (Independent, Instructional,
     * Frustration) are for teachers (see ReadingLevel::forAdult). A child who has not done the first reading check yet has no
     * step: the level on their record is only a guess from the parent's own answers, so it is never shown as if it were measured.
     *
     * @return array{checked: bool, number: ?int, name: string, pill: string, skill: ?string, class: string, text: ?string}
     */
    public static function step(Learner $learner): array
    {
        $checked = app(LearnerDiagnosticService::class)->hasGenuinelyCompletedDiagnostic($learner);
        $number = $checked ? ReadingLevel::step($learner) : null;
        $info = $number ? ReadingLevel::STEPS[$number] : null;

        return [
            'checked' => $number !== null,
            'number' => $number,
            'name' => $info['name'] ?? 'New Reader',
            'pill' => $info['name'] ?? 'First check not done',
            'skill' => $info['skill'] ?? null,
            'class' => $number ? "step-{$number}" : 'step-new',
            'text' => $number ? "Step {$number} of 4: {$info['skill']}" : null,
        ];
    }

    /**
     * The child's step with a plain sentence for a parent. The same array feeds the website's Progress page and the phone app, so a
     * parent reads the same words in both. The older keys (band, label, short, step) stay so an app build made before this change
     * still draws a sensible pill: label and short carry the step name, and band is only a colour hint for those older builds.
     */
    public static function level(Learner $learner): array
    {
        $step = self::step($learner);
        $name = $learner->first_name;

        return [
            'band' => $step['checked'] ? 'instructional' : 'unchecked',
            'label' => $step['pill'],
            'short' => $step['pill'],
            'step' => $step['text'],
            'stepNumber' => $step['number'],
            'name' => $step['name'],
            'skill' => $step['skill'],
            'class' => $step['class'],
            'plain' => [
                1 => "{$name} is learning the sounds of letters. A few minutes of happy practice each day helps most.",
                2 => "{$name} is learning to sound out and read words. Reading a few words together each day helps.",
                3 => "{$name} is reading sentences and growing steadily. Reading a short sentence aloud together helps.",
                4 => "{$name} reads stories and is working on reading smoothly and understanding them. Talking about a story afterwards helps.",
            ][$step['number']] ?? "{$name} has not finished the first reading check yet.",
        ];
    }

    /** The first sentence of a note about a move: "moved up to Sentence Reader". Null when the reading did not change the step. */
    public static function stepMoveText(?int $rungBefore, ?int $rungAfter): ?string
    {
        if ($rungBefore === null || $rungAfter === null) {
            return null;
        }
        $before = ReadingLevel::stepForRung($rungBefore);
        $after = ReadingLevel::stepForRung($rungAfter);
        if ($before === $after) {
            return null;
        }
        $name = ReadingLevel::STEPS[$after]['name'];

        return $after > $before ? "moved up to {$name}" : "moved back to {$name}";
    }

    /**
     * What to practise at home, taken from the kind of mistake the child keeps making across their recent readings (ErrorPatterns).
     * Only said when there is enough evidence, in a parent's words, with one thing to try and the curriculum competency it practises.
     * Null when there is no clear pattern: a few mistakes are not a pattern, and nothing is invented.
     */
    public static function homePractice(Learner $learner): ?array
    {
        $profile = ErrorPatterns::forLearner($learner);
        $main = ErrorPatterns::mainPattern($profile);

        if ($main === null) {
            return null;
        }

        $category = ErrorPatterns::CATEGORIES[$main];

        return [
            'say' => $category['parent'],
            'tip' => $category['tip'],
            'code' => $category['code'],
            'codeText' => $category['codeText'],
            'early' => $profile['early'],
            'examples' => $profile['top'][0]['examples'] ?? [],
            'words' => array_slice(array_keys($profile['missedWords']), 0, 6),
        ];
    }
}
