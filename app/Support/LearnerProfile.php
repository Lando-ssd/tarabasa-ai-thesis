<?php

namespace App\Support;

use App\Http\Controllers\LearnerController;
use App\Models\Learner;
use App\Services\ReadingProgression;

/**
 * What a Teacher is shown about a learner who joins their class: what the Parent said when adding the child, where the
 * first reading check put them, and what suits them now. It is read only and made of data the app already holds; it
 * never changes anything. The point is that a teacher who has just been given a new child starts with an idea of how
 * they read, instead of with a blank page.
 *
 * The Parent's description and the reading check can disagree (a Parent may think the child reads more, or less, than
 * they do). Both are shown, with the check named as the more recent measure; neither is hidden.
 */
class LearnerProfile
{
    /** The Parent's own words for each option on the "How would you describe their reading?" question. */
    public const STAGES = [
        'starting' => 'Cannot yet recognize letters',
        'letters' => 'Recognizes letters but cannot read words yet',
        'blending' => 'Reads simple words',
        'sentences' => 'Reads short sentences',
        'independent' => 'Reads grade-level passages on their own',
        'unsure' => 'The parent was not sure',
    ];

    public const ANSWERS = ['yes' => 'Yes', 'no' => 'No', 'unsure' => 'Not sure'];

    /**
     * @return array{
     *   stage: ?string, language: ?string, supports: list<string>, interests: list<string>,
     *   answers: list<array{question:string, answer:string}>,
     *   check: array{done:bool, step:?string, band:?string, level:?string},
     *   startsAt: ?string, mismatch: ?string,
     *   bestFit: array{step:string, skill:string, code:string, words:string, level:string, advice:string}
     * }
     */
    public static function forTeacher(Learner $learner): array
    {
        $supports = array_values(array_filter(array_map(fn ($k) => LearnerController::SUPPORTS[$k] ?? null, (array) $learner->supports)));
        $interests = array_values(array_filter(array_map(fn ($k) => LearnerController::INTERESTS[$k] ?? null, (array) $learner->interests)));

        $questions = LearnerController::PLACEMENT_QUESTIONS[$learner->grade_level] ?? [];
        $given = is_array($learner->placement_answers) ? $learner->placement_answers : [];
        $answers = [];
        foreach ($questions as $i => $question) {
            $key = 'q'.($i + 1);
            if (isset($given[$key], self::ANSWERS[$given[$key]])) {
                $answers[] = ['question' => $question, 'answer' => self::ANSWERS[$given[$key]]];
            }
        }

        $checkDone = $learner->reading_rung !== null
            || $learner->readingSessions()->where('session_type', 'Diagnostic')->exists();

        $readiness = ReadingLevel::readiness($learner);
        $band = $checkDone ? ReadingLevel::band($learner) : null;

        $startsAt = null;
        $startRung = null;
        if ($learner->reading_stage !== null || $answers !== []) {
            $key = DiagnosticPlacement::startingRung($learner);
            $startRung = array_search($key, DiagnosticPlacement::ladder(), true);
            $startsAt = $startRung === false ? null : ReadingLevel::stepNameForRung((int) $startRung);
        }

        // The check against what the Parent described: only said when they are clearly apart (two or more rungs).
        $mismatch = null;
        if ($checkDone && $learner->reading_rung !== null && $startRung !== null && $startRung !== false) {
            $gap = (int) $learner->reading_rung - (int) $startRung;
            if ($gap <= -2) {
                $mismatch = "The reading check placed {$learner->first_name} lower than the parent described. The check is the more recent measure, and the activities follow it.";
            } elseif ($gap >= 2) {
                $mismatch = "The reading check placed {$learner->first_name} higher than the parent described. The check is the more recent measure, and the activities follow it.";
            }
        }

        return [
            'stage' => self::STAGES[$learner->reading_stage] ?? null,
            'language' => $learner->home_language,
            'supports' => $supports,
            'interests' => $interests,
            'answers' => $answers,
            'check' => [
                'done' => $checkDone,
                'step' => $checkDone ? ReadingLevel::stepName($learner) : null,
                'band' => $band ? ReadingLevel::bandLabel($band) : null,
                'level' => $checkDone ? $learner->mastery_level : null,
            ],
            'startsAt' => $startsAt,
            'mismatch' => $mismatch,
            'bestFit' => self::bestFit($learner, $readiness),
            'teach' => self::teach($learner, $readiness),
            'pattern' => self::pattern($learner),
            'progress' => $checkDone || $learner->reading_rung !== null ? app(ReadingProgression::class)->progress($learner) : null,
        ];
    }

    /**
     * The kind of mistake the child keeps making across their recent readings (ErrorPatterns), in a teacher's words with
     * one thing to try and the curriculum competency it practises. Null when there is not enough evidence: a few
     * mistakes are not a pattern, and nothing is invented.
     *
     * @return null|array{says:string, tip:string, code:string, codeText:string, early:bool, examples:list<string>, readings:int}
     */
    private static function pattern(Learner $learner): ?array
    {
        $profile = ErrorPatterns::forLearner($learner);
        $main = ErrorPatterns::mainPattern($profile);

        if ($main === null) {
            return null;
        }

        $category = ErrorPatterns::CATEGORIES[$main];

        return [
            'says' => $category['teacher'],
            'tip' => $category['tip'],
            'code' => $category['code'],
            'codeText' => $category['codeText'],
            'early' => $profile['early'],
            'examples' => $profile['top'][0]['examples'] ?? [],
            'readings' => $profile['readings'],
        ];
    }

    /**
     * How to teach at the child's step, from config/teaching_path.php, with the "ready for the next step" line written from
     * the same numbers the app counts (config/progression.php), so what the teacher reads is what is being measured.
     *
     * @param  null|array{rung:int, source:string, sourceText:string}  $readiness
     * @return array{element:string, focus:string, moves:list<string>, ready:string, watch:string, basis:string}
     */
    private static function teach(Learner $learner, ?array $readiness): array
    {
        $rung = $readiness['rung'] ?? 2;
        $path = config("teaching_path.rungs.{$rung}");
        $min = config("progression.min_words.{$rung}");

        $ready = $path['ready'] ?? sprintf(
            'Reads %d words or more at %d percent or better, %d times, in at least %d different activities. One reading is never enough to move a child.',
            $min,
            config('progression.up_accuracy'),
            config('progression.up_readings'),
            config('progression.up_distinct_activities'),
        );

        return [
            'element' => $path['element'],
            'focus' => $path['focus'],
            'moves' => $path['moves'],
            'ready' => $ready,
            'watch' => $path['watch'],
            'basis' => config('teaching_path.basis'),
        ];
    }

    /**
     * What suits the child now, in the MATATAG order of learning to read (letter names, sounding out words, sentences,
     * stories), and how long an activity can be before it is too much. The words come from the same limits that stop an
     * activity that is too long from being assigned (config/activity_fit.php).
     *
     * @param  null|array{rung:int, source:string, sourceText:string}  $readiness
     */
    private static function bestFit(Learner $learner, ?array $readiness): array
    {
        $rung = $readiness['rung'] ?? 2;
        $stepNo = ReadingLevel::stepForRung($rung);
        $step = ReadingLevel::STEPS[$stepNo];
        [$comfortable, $stretch] = ActivityFit::limits($rung);

        $level = match (true) {
            $rung <= 2 => 'Easy',
            $rung <= 4 => 'Medium',
            default => 'Hard',
        };

        $advice = match ($stepNo) {
            1 => 'Start with the names and sounds of letters, then very short word lists. Say the letters aloud together before the child reads alone.',
            2 => 'Short lists of simple words to sound out. Read the first one together, then let the child try.',
            3 => 'Short sentences of familiar words, read twice: once with you, once alone.',
            default => 'Short stories, and a question or two about what happened.',
        };

        return [
            'step' => $step['name'],
            'skill' => $step['skill'],
            'code' => $step['code'],
            'words' => "about {$comfortable} words at a time is comfortable; up to {$stretch} is a stretch",
            'level' => $level,
            'advice' => $advice,
        ];
    }
}
