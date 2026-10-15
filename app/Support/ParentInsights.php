<?php

namespace App\Support;

use App\Models\Learner;

/**
 * What a parent is told about how their child is reading, in a parent's words. The website's Progress page and the phone app's both
 * call this, so the sentence a parent reads about their child's level is the same on both.
 */
class ParentInsights
{
    /**
     * The child's reading level in the school's own words (Phil-IRI, see ReadingLevel) with a plain sentence for a parent. The Phil-IRI
     * names can sound harsh to a parent ("frustration level"), so each comes with what it means in everyday words.
     */
    public static function level(Learner $learner): array
    {
        $level = ReadingLevel::forAdult($learner);
        $name = $learner->first_name;

        $level['plain'] = [
            'non' => "{$name} is just starting with letters and sounds. Short, happy practice every day helps most.",
            'frustration' => "Reading is still hard for {$name} right now. That is common while learning, and short daily practice helps.",
            'instructional' => "{$name} reads well with some help and is learning steadily.",
            'independent' => "{$name} reads comfortably alone.",
            'unchecked' => "{$name} has not finished the first reading check yet.",
        ][$level['band']] ?? '';

        return $level;
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
