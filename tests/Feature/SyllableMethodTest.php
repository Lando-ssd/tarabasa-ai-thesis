<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Learner;
use App\Models\ReadingSession;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use App\Services\PracticeGameService;
use App\Support\ErrorPatterns;
use App\Support\GenerationBasis;
use App\Support\LearnerCode;
use App\Support\LearnerProfile;
use App\Support\SyllableMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The syllable method (segment and blend, taught through repeated drill, inside structured phonics). These pin what the
 * team's working sheet says: the word list obeys the two corrections (CV + CV is not CVCVC, English starts with closed
 * syllables), the drill text is the sheet's, only the whole word is ever scored, and the method reaches the mistake
 * patterns, the activity writer's note, the teacher's learner page and the games.
 */
class SyllableMethodTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    // ------------------------------------------------------------- the word list obeys the sheet

    public function test_every_word_is_its_two_parts_joined_and_appears_in_its_sentence(): void
    {
        foreach (SyllableMethod::words() as $w) {
            $this->assertCount(2, $w['parts'], $w['word']);
            $this->assertSame($w['word'], implode('', $w['parts']), $w['word']);
            $this->assertStringContainsString($w['word'], strtolower($w['sentence']), $w['word']);
            $this->assertGreaterThanOrEqual(3, str_word_count($w['sentence']), $w['word']);
            // Plain words only: no digits and no capitalised names after the first word, where a recognizer is weakest.
            $this->assertDoesNotMatchRegularExpression('/\d/', $w['sentence'], $w['word']);
        }
    }

    public function test_the_word_shapes_follow_the_corrected_method(): void
    {
        $consonant = '[^aeiou]';

        foreach (SyllableMethod::words(1) as $w) {
            // Level 1: two closed syllables, CVC + CVC.
            $this->assertMatchesRegularExpression("/^{$consonant}[aeiou]{$consonant}$/", $w['parts'][0], $w['word']);
            $this->assertMatchesRegularExpression("/^{$consonant}[aeiou]{$consonant}$/", $w['parts'][1], $w['word']);
        }

        foreach (SyllableMethod::words(2) as $w) {
            // Level 2: a closed syllable, then CVCe (a silent e).
            $this->assertMatchesRegularExpression("/^{$consonant}[aeiou]{$consonant}$/", $w['parts'][0], $w['word']);
            $this->assertMatchesRegularExpression("/^{$consonant}+[aeiou]{$consonant}e$/", $w['parts'][1], $w['word']);
        }

        foreach (SyllableMethod::words(3) as $w) {
            // Level 3: CVC + VC, which is CVCVC (the lemon, melon, cabin, rapid words), never the CV + CV of kami.
            $this->assertMatchesRegularExpression("/^{$consonant}[aeiou]{$consonant}$/", $w['parts'][0], $w['word']);
            $this->assertMatchesRegularExpression("/^[aeiou]{$consonant}$/", $w['parts'][1], $w['word']);
        }
    }

    public function test_there_are_no_open_syllables_in_the_english_bank(): void
    {
        // Correction 2: English starts with closed syllables. A part that ends in a vowel (ba in baby) is an open syllable. The only
        // vowel allowed at the end of a part is the silent e of a CVCe second part.
        foreach (SyllableMethod::words() as $w) {
            foreach ($w['parts'] as $i => $part) {
                $endsInVowel = preg_match('/[aeiou]$/', $part) === 1;
                $silentE = $w['level'] === 2 && $i === 1 && str_ends_with($part, 'e');
                $this->assertTrue(! $endsInVowel || $silentE, "{$w['word']}: {$part} is an open syllable");
            }
        }

        // And the Filipino examples (ka-mi, ki-ta) are reference only: they are CV + CV and are not in the English bank.
        foreach (config('syllable_method.filipino_reference') as $ref) {
            $this->assertFalse(SyllableMethod::isBankWord($ref['word']));
        }
    }

    public function test_the_sheets_own_words_are_in_the_bank_with_the_sheets_splits(): void
    {
        $expected = [
            'sunset' => 'sun-set', 'hotdog' => 'hot-dog', 'picnic' => 'pic-nic', 'rabbit' => 'rab-bit', 'napkin' => 'nap-kin',
            'kitten' => 'kit-ten', 'basket' => 'bas-ket', 'cupcake' => 'cup-cake', 'lemon' => 'lem-on', 'cabin' => 'cab-in',
            'rapid' => 'rap-id', 'melon' => 'mel-on',
        ];

        foreach ($expected as $word => $split) {
            $this->assertSame($split, SyllableMethod::segmented(SyllableMethod::partsOf($word) ?? []), $word);
        }

        // Levels as in the sheet: seven at level 1 to start with, cupcake at 2, the CVCVC words at 3.
        $this->assertSame(1, SyllableMethod::find('sunset')['level']);
        $this->assertSame(2, SyllableMethod::find('cupcake')['level']);
        $this->assertSame(3, SyllableMethod::find('lemon')['level']);
    }

    // ------------------------------------------------------------- the drill

    public function test_the_drill_text_is_the_sheets(): void
    {
        $this->assertSame('sun sun sun sun set set set set', SyllableMethod::drillText(['sun', 'set']));
        $this->assertSame('hot hot hot hot dog dog dog dog', SyllableMethod::drillText(['hot', 'dog'], 4));
        $this->assertSame('cup cup cake cake', SyllableMethod::drillText(['cup', 'cake'], 2));
        $this->assertSame('sun-set', SyllableMethod::segmented(['sun', 'set']));
    }

    public function test_the_routine_has_four_steps_and_only_the_whole_word_is_scored(): void
    {
        $routine = SyllableMethod::routine(SyllableMethod::find('sunset'));

        $this->assertCount(4, $routine);
        $this->assertSame([false, false, false, true], array_column($routine, 'scored'));
        $this->assertStringContainsString('sun sun sun sun set set set set', $routine[0]['text']);
        $this->assertStringContainsString('sun-set', $routine[1]['text']);
        $this->assertStringContainsString('sunset', $routine[2]['text']);
        $this->assertStringContainsString('We watch the sunset.', $routine[3]['text']);
    }

    public function test_the_word_level_rises_with_the_step_and_letters_have_none(): void
    {
        $this->assertNull(SyllableMethod::levelForRung(null));
        $this->assertNull(SyllableMethod::levelForRung(0));
        $this->assertSame(1, SyllableMethod::levelForRung(1));
        $this->assertSame(1, SyllableMethod::levelForRung(2));
        $this->assertSame(2, SyllableMethod::levelForRung(3));
        $this->assertSame(2, SyllableMethod::levelForRung(4));
        $this->assertSame(3, SyllableMethod::levelForRung(5));
        $this->assertSame(3, SyllableMethod::levelForRung(6));

        $this->assertSame([], SyllableMethod::wordsForRung(0));
        // Easier levels stay for review.
        $this->assertTrue(collect(SyllableMethod::wordsForRung(5))->contains('word', 'sunset'));
        $this->assertFalse(collect(SyllableMethod::wordsForRung(1))->contains('word', 'lemon'));
    }

    public function test_the_note_for_the_activity_writer_carries_the_two_rules(): void
    {
        $note = SyllableMethod::writerNote(2);

        $this->assertStringContainsString('sunset (sun-set)', $note);
        $this->assertStringContainsString('CVC + CVC', $note);
        $this->assertStringContainsString('Avoid open syllables', $note);
        $this->assertStringContainsString('CVC + CVCe', SyllableMethod::writerNote(4));
        $this->assertNull(SyllableMethod::writerNote(0));
    }

    // ------------------------------------------------------------- the recognisers

    public function test_what_looks_like_a_longer_word(): void
    {
        foreach (['sunset', 'table', 'jumping', 'cupcake', 'garden', 'happy'] as $word) {
            $this->assertTrue(SyllableMethod::looksMultiSyllable($word), $word);
        }
        foreach (['cat', 'cake', 'make', 'the', 'house', 'stone', 'play', 'frog'] as $word) {
            $this->assertFalse(SyllableMethod::looksMultiSyllable($word), $word);
        }
    }

    public function test_a_part_of_a_longer_word_is_not_a_different_word(): void
    {
        $this->assertTrue(SyllableMethod::isPartOf('sunset', 'sun'));
        $this->assertTrue(SyllableMethod::isPartOf('sunset', 'set'));
        $this->assertTrue(SyllableMethod::isPartOf('kitten', 'kit'));
        // A part the recognizer wrote as a word that sounds the same (sun heard as son) is still that part.
        $this->assertTrue(SyllableMethod::isPartOf('sunset', 'son'));
        $this->assertFalse(SyllableMethod::isPartOf('sunset', 'sunsets'));
        $this->assertFalse(SyllableMethod::isPartOf('sunset', 'sunset'));
        $this->assertFalse(SyllableMethod::isPartOf('cat', 'c'));
        $this->assertFalse(SyllableMethod::isPartOf('sunset', 'sm'));
    }

    public function test_two_pieces_that_join_into_the_word_are_a_word_read_in_pieces(): void
    {
        $this->assertTrue(SyllableMethod::readInPieces('sunset', 'sun', 'set'));
        $this->assertTrue(SyllableMethod::readInPieces('basket', 'bass', 'kit'));
        $this->assertTrue(SyllableMethod::readInPieces('basket', 'kit', 'bass'), 'the extra word may come first');
        $this->assertFalse(SyllableMethod::readInPieces('sunset', 'sun', 'x'));
        $this->assertFalse(SyllableMethod::readInPieces('cat', 'c', 'at'));
        $this->assertFalse(SyllableMethod::readInPieces('sunset', 'dog', 'run'));
    }

    // ------------------------------------------------------------- the mistake pattern

    public function test_longer_words_in_pieces_is_a_mistake_pattern_with_its_code(): void
    {
        $this->assertArrayHasKey('syllables', ErrorPatterns::CATEGORIES);
        $c = ErrorPatterns::CATEGORIES['syllables'];
        $this->assertSame('Longer words in pieces', $c['label']);
        $this->assertSame('RL1PWS-I-5', $c['code']);
        $this->assertStringContainsString('RL1PA-I-2', $c['codeText']);
    }

    public function test_only_a_part_of_a_longer_word_is_called_syllables_and_endings_still_win(): void
    {
        $sub = fn (string $ref, string $spoken) => ErrorPatterns::classify(['reference' => $ref, 'spoken' => $spoken, 'status' => 'substitution']);

        $this->assertSame('syllables', $sub('sunset', 'sun'));
        $this->assertSame('syllables', $sub('kitten', 'ten'));
        // An ending left off is still an ending, and the older patterns are unchanged.
        $this->assertSame('endings', $sub('jumping', 'jump'));
        $this->assertSame('blends', $sub('frog', 'fog'));
        $this->assertSame('vowels', $sub('hat', 'hit'));
        $this->assertNull(ErrorPatterns::classify(['reference' => 'sunset', 'spoken' => 'sunset', 'status' => 'correct']));
        // A word skipped is still skipped.
        $this->assertSame('skipped', ErrorPatterns::classify(['reference' => 'sunset', 'status' => 'deletion']));
    }

    public function test_a_word_the_recognizer_wrote_as_two_pieces_is_marked_before_it_is_sorted(): void
    {
        $entries = ErrorPatterns::markPieces([
            ['reference' => 'we', 'spoken' => 'we', 'status' => 'correct'],
            ['reference' => 'basket', 'spoken' => 'bass', 'status' => 'substitution'],
            ['reference' => null, 'spoken' => 'kit', 'status' => 'insertion'],
            ['reference' => 'big', 'spoken' => 'bag', 'status' => 'substitution'],
        ]);

        $this->assertTrue($entries[1]['in_pieces']);
        $this->assertArrayNotHasKey('in_pieces', $entries[3]);
        $this->assertSame('syllables', ErrorPatterns::classify($entries[1]));
        $this->assertSame('vowels', ErrorPatterns::classify($entries[3]));
    }

    public function test_a_child_who_keeps_reading_longer_words_in_pieces_gets_the_pattern_with_evidence(): void
    {
        $feedback = [
            ['reference' => 'sunset', 'spoken' => 'sun', 'status' => 'substitution'],
            ['reference' => 'kitten', 'spoken' => 'kit', 'status' => 'substitution'],
            ['reference' => 'basket', 'spoken' => 'bass', 'status' => 'substitution'],
            ['reference' => null, 'spoken' => 'kit', 'status' => 'insertion'],
            ['reference' => 'we', 'spoken' => 'we', 'status' => 'correct'],
        ];
        $sessions = collect([(object) ['word_feedback' => $feedback], (object) ['word_feedback' => $feedback], (object) ['word_feedback' => $feedback]]);

        $profile = ErrorPatterns::profile($sessions);

        $this->assertTrue($profile['enough']);
        $this->assertSame('syllables', ErrorPatterns::mainPattern($profile));
        $this->assertSame(100, $profile['top'][0]['share']);
        $this->assertContains('sunset as sun', $profile['top'][0]['examples']);
    }

    public function test_the_games_and_the_activity_fit_know_a_longer_word(): void
    {
        $this->assertTrue(ErrorPatterns::practisesPattern('sunset', 'syllables'));
        $this->assertFalse(ErrorPatterns::practisesPattern('cat', 'syllables'));
        $this->assertSame(['sunset', 'cat'], ErrorPatterns::orderWords(['cat', 'sunset'], 'syllables'));
    }

    // ------------------------------------------------------------- the games

    private function teacher(): Teacher
    {
        $n = ++$this->seq;
        $u = User::create(['first_name' => 'T', 'last_name' => "Syl{$n}", 'email' => "syl{$n}@example.com", 'password' => 'Passw0rd!', 'user_type' => 'Teacher']);
        $u->forceFill(['email_verified_at' => now()])->save();

        return Teacher::create(['user_id' => $u->id, 'school_name' => 'S', 'employee_id' => "SY{$n}", 'status' => 'Active', 'grades_handled' => ['Grade 1', 'Grade 2']]);
    }

    private function learner(SchoolClass $class, string $first, int $rung = 2): Learner
    {
        return Learner::create([
            'learner_code' => LearnerCode::generate(), 'class_id' => $class->id, 'first_name' => $first, 'last_name' => 'Cruz', 'grade_level' => $class->grade_level,
            'pin' => '1234', 'avatar_id' => 'A', 'mastery_level' => 'Developing', 'reading_rung' => $rung,
        ]);
    }

    private function classFor(Teacher $t): SchoolClass
    {
        return SchoolClass::create(['teacher_id' => $t->id, 'name' => 'Sampaguita', 'grade_level' => 'Grade 1', 'section' => 'A', 'school_year' => SchoolClass::currentSchoolYear()]);
    }

    /** Three practice readings in which the child reads longer words in pieces. */
    private function piecesMistakes(Learner $kid): void
    {
        $activity = Activity::create(['created_by_teacher_id' => null, 'grade_level' => 'Grade 1', 'competency' => 'foundational_reading', 'competency_label' => 'Foundational Reading', 'activity_type' => 'word_reading', 'title' => 'Plain', 'instructions' => 'Read the words.', 'passage_text' => 'sunset kitten', 'reference_text' => 'sunset kitten', 'word_count' => 2, 'status' => 'Approved', 'difficulty_tier' => 'Easy']);
        $feedback = [
            ['reference' => 'sunset', 'spoken' => 'sun', 'status' => 'substitution'],
            ['reference' => 'kitten', 'spoken' => 'kit', 'status' => 'substitution'],
            ['reference' => 'napkin', 'spoken' => 'nap', 'status' => 'substitution'],
            ['reference' => 'basket', 'spoken' => 'bas', 'status' => 'substitution'],
        ];

        for ($i = 0; $i < 3; $i++) {
            $row = ReadingSession::create(['learner_id' => $kid->id, 'activity_id' => $activity->id, 'session_type' => 'Practice', 'initiated_by' => 'Teacher', 'accuracy_percent' => 50, 'word_feedback' => $feedback]);
            $row->timestamp = now()->subDays($i + 1);
            $row->save();
        }
    }

    public function test_a_child_who_reads_in_pieces_is_offered_the_methods_words_and_sentences_first(): void
    {
        $t = $this->teacher();
        $kid = $this->learner($this->classFor($t), 'Pia', 2);
        $this->piecesMistakes($kid);

        $this->assertSame('syllables', ErrorPatterns::mainPattern(ErrorPatterns::forLearner($kid)));

        $game = app(PracticeGameService::class);
        $words = $game->wordLevels($kid);

        // The level-1 pool is three letter words; the method's two-syllable words are favoured on top of it, easier levels only.
        $this->assertContains('sunset', $words[1]['favoured']);
        // A word the checker did not hear reliably on its own is not used in a game that checks one word at a time.
        $this->assertNotContains('kitten', $words[1]['favoured']);
        $this->assertNotContains('mitten', array_column(SyllableMethod::words(), 'word'));
        $this->assertNotContains('lemon', $words[1]['favoured']);
        $this->assertContains('lemon', $words[3]['favoured']);

        $sentences = $game->sentenceLevels($kid);
        $this->assertContains('We watch the sunset.', $sentences[1]['preferred']);
        $this->assertContains('We watch the sunset.', $sentences[1]['fallback']);
    }

    public function test_a_child_with_no_such_pattern_gets_the_games_exactly_as_before(): void
    {
        $t = $this->teacher();
        $kid = $this->learner($this->classFor($t), 'Pia', 2);

        $game = app(PracticeGameService::class);

        $this->assertNotContains('sunset', $game->wordLevels($kid)[1]['favoured']);
        $this->assertNotContains('We watch the sunset.', $game->sentenceLevels($kid)[1]['fallback']);
    }

    // ------------------------------------------------------------- the activity writer and the teacher

    public function test_word_lists_for_a_class_ask_the_writer_for_two_syllable_words(): void
    {
        $t = $this->teacher();
        $class = $this->classFor($t);
        $this->learner($class, 'Ana', 2);
        $this->learner($class, 'Ben', 2);

        $basis = GenerationBasis::forClass($class, 'word_reading');

        $this->assertStringContainsString('Syllable method (RL1PA-I-2, RL1PWS-I-5)', implode(' ', $basis['lines']));
        $this->assertStringContainsString('sunset (sun-set)', $basis['note']);
        $this->assertStringContainsString('Avoid open syllables', $basis['note']);
        $this->assertLessThanOrEqual(560, strlen($basis['note']));
        // The note is whole sentences, never cut off in the middle of a word.
        $this->assertStringEndsWith('.', $basis['note']);
    }

    public function test_a_passage_for_a_class_does_not_get_the_syllable_note_unless_children_read_in_pieces(): void
    {
        $t = $this->teacher();
        $class = $this->classFor($t);
        $this->learner($class, 'Ana', 2);
        $this->learner($class, 'Ben', 2);

        $plain = GenerationBasis::forClass($class, 'passage_reading');
        $this->assertStringNotContainsString('Syllable method', implode(' ', $plain['lines']));
        $this->assertStringNotContainsString('sunset', (string) $plain['note']);

        // When two children in the class read longer words in pieces, even a passage gets it.
        foreach ($class->learners as $kid) {
            $this->piecesMistakes($kid);
        }

        $shared = GenerationBasis::forClass($class, 'passage_reading');
        $this->assertStringContainsString('Syllable method', implode(' ', $shared['lines']));
        $this->assertStringContainsString('sunset (sun-set)', $shared['note']);
        $this->assertStringContainsString('2 learners', implode(' ', $shared['lines']));
    }

    public function test_a_class_still_on_letters_gets_no_syllable_note(): void
    {
        $t = $this->teacher();
        $class = $this->classFor($t);
        $this->learner($class, 'Ana', 0);
        $this->learner($class, 'Ben', 0);

        $basis = GenerationBasis::forClass($class, 'word_reading');

        $this->assertStringNotContainsString('Syllable method', implode(' ', $basis['lines']));
    }

    public function test_the_teachers_learner_profile_has_the_routine_for_the_childs_step(): void
    {
        $t = $this->teacher();
        $class = $this->classFor($t);

        $two = LearnerProfile::forTeacher($this->learner($class, 'Ana', 2))['syllables'];
        $this->assertSame(1, $two['level']);
        $this->assertSame('CVC + CVC', $two['pattern']);
        $this->assertCount(4, $two['routine']);
        $this->assertFalse($two['suggested']);
        $this->assertContains('sunset (sun-set)', $two['words']);

        $five = LearnerProfile::forTeacher($this->learner($class, 'Eli', 5))['syllables'];
        $this->assertSame(3, $five['level']);

        // A child still on the Letters step has no word routine (they clap the parts of spoken words instead).
        $this->assertNull(LearnerProfile::forTeacher($this->learner($class, 'Lia', 0))['syllables']);
        $this->assertTrue(collect(config('teaching_path.rungs.0.moves'))->contains(fn ($m) => str_contains($m, 'Clap the parts of spoken words')));

        // A child whose readings show the pattern is told the routine is suggested.
        $kid = $this->learner($class, 'Pia', 2);
        $this->piecesMistakes($kid);
        $this->assertTrue(LearnerProfile::forTeacher($kid)['syllables']['suggested']);
    }

    public function test_the_routine_shows_on_the_teachers_class_window(): void
    {
        $t = $this->teacher();
        $class = $this->classFor($t);
        $kid = $this->learner($class, 'Pia', 2);
        $this->piecesMistakes($kid);

        $this->actingAs($t->user)->get(route('teacher.classes.index', ['open' => $class->id, 'tab' => 'learner-'.$kid->id]))
            ->assertOk()
            ->assertSee('Syllable routine for longer words')
            ->assertSee('Pia reads longer words in pieces')
            ->assertSee('sun sun sun sun set set set set');
    }
}
