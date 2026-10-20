<?php

namespace App\Support;

use App\Http\Controllers\LearnerController;
use App\Models\SchoolClass;
use Illuminate\Support\Str;

/**
 * What a class's own readings say about the activities it needs: the basis of "write it for my class".
 *
 * The activity generator knows nothing about the children. It takes a grade, a skill, an activity type and
 * one free text field (`teacher_notes`). This reads what the app already knows about a class (the step on
 * the reading path each child is on, the kinds of mistakes they repeat, the words several of them missed,
 * the topics they like) and turns it into two things:
 *
 *  - `lines`: plain sentences the Teacher reads BEFORE pressing Generate, and later on each activity
 *    ("Written for Sampaguita because..."), so nothing about the class is hidden or taken on trust;
 *  - `note`: a short instruction for the generator's `teacher_notes`, built from the same facts.
 *
 * It is rules over real data, not a trained model (the project has no dataset of children's readings to
 * train one on), and it says so: every line names a count of real learners, and a thing is only said about
 * the class when at least MIN_SHARED children show it, so one child's habit is never presented as the class's.
 * The same rules the rest of the app uses make the facts (ReadingLevel for the step, ErrorPatterns for the
 * mistakes, ActivityFit's limits for how long a text suits), so this cannot disagree with the alerts or the
 * "can this child read it?" checks.
 */
class GenerationBasis
{
    /** Children who must show the same thing before it counts as the class's. */
    public const MIN_SHARED = 2;

    /** The most the note may take of the generator's 1000 character notes field, leaving room for the Teacher's own words. */
    private const NOTE_LIMIT = 560;

    /** The activity types that are lists of words, where the syllable method's two-syllable words fit. */
    private const SYLLABLE_TYPES = ['word_reading', 'phonics_reading'];

    /**
     * @return array{
     *   className: string, grade: string, learners: int, withReadings: int, enough: bool,
     *   lines: list<string>, note: ?string
     * }
     */
    public static function forClass(SchoolClass $class, ?string $activityType = null): array
    {
        $learners = $class->learners()->get();
        $total = $learners->count();

        $base = ['className' => $class->name, 'grade' => $class->grade_level, 'learners' => $total, 'withReadings' => 0, 'enough' => false, 'lines' => [], 'note' => null];

        if ($total === 0) {
            $base['lines'] = ['This class has no learners yet, so there is nothing to base the activities on.'];

            return $base;
        }

        $lines = [];
        $note = [];

        // The step on the reading path each child is on (the first reading check, or what the parent said
        // before there is a check): the same answer ActivityFit uses when it decides a text is too long.
        $rungs = $learners->map(fn ($l) => ReadingLevel::readiness($l)['rung'] ?? null)->filter(fn ($r) => $r !== null)->sort()->values();

        // Each child's own mistake profile, the one way every screen works it out.
        $profiles = $learners->map(fn ($l) => ErrorPatterns::forLearner($l));
        $withReadings = $profiles->filter(fn ($p) => $p['readings'] > 0)->count();
        $base['withReadings'] = $withReadings;

        // Mistake kinds that several different children repeat: how many children have each as their main pattern.
        $kinds = [];
        foreach ($profiles as $profile) {
            $key = ErrorPatterns::mainPattern($profile);
            if ($key !== null) {
                $kinds[$key] = ($kinds[$key] ?? 0) + 1;
            }
        }
        arsort($kinds);

        $lines[] = $withReadings === 0
            ? "{$total} ".Str::plural('learner', $total).' in the class, none with a practice reading yet.'
            : "{$total} ".Str::plural('learner', $total).' in the class, '.$withReadings.' with practice readings so far.';

        if ($rungs->isNotEmpty()) {
            $median = (int) $rungs[intdiv($rungs->count() - 1, 2)];
            [$comfortable, $stretch] = ActivityFit::limits($median);
            $label = ReadingLevel::RUNG_LABELS[$median] ?? 'Words';
            $same = $rungs->filter(fn ($r) => $r === $median)->count();

            $lines[] = "Most read at the {$label} step ({$same} of {$rungs->count()} with a known step). About {$comfortable} words is comfortable for that step and up to {$stretch} is a stretch.";
            // What suits the step, in the words a reading teacher would use for it.
            $suits = match (true) {
                $median <= 0 => 'use single letters or very short words that are easy to say, and no sentences',
                $median === 1 => 'use short words that are easy to sound out',
                default => 'use simple familiar words and short, plain sentences',
            };
            $note[] = "Most children in this class read at the {$label} step, so {$suits}.";

            $suit = self::levelsSuit($activityType, $class->grade_level, $median, $comfortable, $stretch);
            if ($suit !== null) {
                $lines[] = $suit;
            }

            // The syllable method (config/syllable_method.php): for word lists at a step that has a word level, or when several
            // children read longer words in pieces, the writer is asked for two-syllable words that split cleanly.
            $sharesSyllables = ($kinds['syllables'] ?? 0) >= self::MIN_SHARED;
            $syllableNote = SyllableMethod::writerNote($median);
            if ($syllableNote !== null && ($sharesSyllables || in_array($activityType, self::SYLLABLE_TYPES, true))) {
                $level = SyllableMethod::levelForRung($median);
                $shape = config("syllable_method.levels.{$level}.pattern");
                $first = SyllableMethod::words($level)[0];
                $lines[] = "Syllable method (RL1PA-I-2, RL1PWS-I-5): word lists use two-syllable words that split cleanly ({$shape}), for example {$first['word']} ({$first['parts'][0]}-{$first['parts'][1]}). The learner hears and repeats each part, splits the word, joins it, then reads it in a sentence.";
                $note[] = $syllableNote;
            }
        }

        // Mistake kinds that several different children repeat (counted above, with the step).
        foreach (array_slice(array_filter($kinds, fn ($n) => $n >= self::MIN_SHARED), 0, 2, true) as $key => $count) {
            $category = ErrorPatterns::CATEGORIES[$key];
            $lines[] = "{$count} learners {$category['teacher']}.";
            $note[] = "Several children {$category['teacher']}: include words that practise this ({$category['label']}).";
        }

        // Words that several children missed.
        $missed = [];
        foreach ($profiles as $profile) {
            foreach (array_keys($profile['missedWords']) as $word) {
                $missed[$word] = ($missed[$word] ?? 0) + 1;
            }
        }
        arsort($missed);
        $shared = array_slice(array_keys(array_filter($missed, fn ($n) => $n >= self::MIN_SHARED)), 0, 6);
        if ($shared !== []) {
            $lines[] = 'Words several learners missed: '.implode(', ', $shared).'.';
            $note[] = 'Words the children missed and should meet again: '.implode(', ', $shared).'.';
        }

        // Topics the parents said their children like.
        $topics = [];
        foreach ($learners as $learner) {
            foreach ((array) $learner->interests as $key) {
                if (isset(LearnerController::INTERESTS[$key])) {
                    $topics[$key] = ($topics[$key] ?? 0) + 1;
                }
            }
        }
        arsort($topics);
        $liked = array_slice(array_keys(array_filter($topics, fn ($n) => $n >= self::MIN_SHARED)), 0, 3);
        if ($liked !== []) {
            $names = array_map(fn ($k) => mb_strtolower(LearnerController::INTERESTS[$k]), $liked);
            $lines[] = 'Topics they like: '.implode(', ', $names).'.';
            $note[] = 'Topics the children like: '.implode(', ', $names).'.';
        }

        $base['lines'] = $lines;
        // Only the step, the mistakes, the words and the topics are worth sending. A class where none of them was found
        // would send nothing the generator does not already know, so the request goes out as a plain one.
        $base['enough'] = $note !== [];
        $base['note'] = $note === [] ? null : self::fitNote('From this class\'s real readings:', $note);

        return $base;
    }

    /**
     * The note for the generator: the lead-in, then as many of the sentences as fit in NOTE_LIMIT, in the order of importance. A
     * sentence that does not fit is left out whole (a later, shorter one may still fit), never cut off in the middle of a word.
     *
     * @param  list<string>  $sentences
     */
    private static function fitNote(string $lead, array $sentences): string
    {
        $out = $lead;

        foreach ($sentences as $sentence) {
            if (strlen($out) + 1 + strlen($sentence) <= self::NOTE_LIMIT) {
                $out .= ' '.$sentence;
            }
        }

        // Even the first sentence did not fit: send it trimmed rather than send nothing.
        return $out === $lead ? Str::limit($lead.' '.$sentences[0], self::NOTE_LIMIT, '') : $out;
    }

    /**
     * Which of the three levels a class's step can read, from the usual word range of each level for this grade and type.
     * Said to the Teacher only: it helps choose how many of each level to ask for, and is not sent to the generator, which
     * has its own word ranges per level.
     */
    private static function levelsSuit(?string $type, string $grade, int $rung, int $comfortable, int $stretch): ?string
    {
        $bands = config('activity_levels.bands')[$type] ?? null;
        $gradeNo = (int) substr($grade, 6);
        $perGrade = $bands[$gradeNo] ?? null;

        if ($perGrade === null) {
            return null;
        }

        $weight = in_array($rung, config('activity_fit.connected_text_rungs'), true) && in_array($type, config('activity_fit.connected_text_types'), true)
            ? (float) config('activity_fit.connected_text_weight') : 1.0;

        $groups = ['suit' => [], 'stretch' => [], 'long' => []];
        foreach (config('activity_levels.tiers') as $tier) {
            $top = $perGrade[strtolower($tier)][1] * $weight;
            $groups[match (true) {
                $top <= $comfortable => 'suit',
                $top <= $stretch => 'stretch',
                default => 'long',
            }][] = $tier;
        }

        $parts = [];
        if ($groups['suit']) {
            $parts[] = 'suits most of the class: '.implode(' and ', $groups['suit']);
        }
        if ($groups['stretch']) {
            $parts[] = 'a stretch: '.implode(' and ', $groups['stretch']);
        }
        if ($groups['long']) {
            $parts[] = 'likely too long for many: '.implode(' and ', $groups['long']);
        }

        return 'For this activity type, '.implode('; ', $parts).'.';
    }

    /**
     * The notes sent to the generator: the Teacher's own words first, then what the class's readings say, then the
     * Teacher's earlier re-leveling, each trimmed to what is left of the generator's 1000 characters.
     *
     * @param  list<?string>  $parts
     */
    public static function composeNotes(array $parts, int $limit = 1000): ?string
    {
        $out = '';

        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }

            $room = $limit - strlen($out) - ($out === '' ? 0 : 1);
            if ($room < 40) {
                break;
            }

            $out .= ($out === '' ? '' : "\n").Str::limit($part, $room, '');
        }

        return $out === '' ? null : $out;
    }
}
