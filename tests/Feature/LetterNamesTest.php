<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Learner;
use App\Models\ReadingSession;
use App\Services\LearnerDiagnosticService;
use App\Services\PracticeGameService;
use App\Services\ReadingAiClient;
use App\Support\LearnerCode;
use App\Support\LetterNames;
use App\Support\NotSure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Letter names: the speech service writes "as" for S and "cb" for C then B (measured on the live service with synthesized voices,
 * 2026-10-23), and marked a child wrong who named every letter. LetterNames looks again, and can only add credit.
 *
 * The three real fixtures below are what the live service actually returned, copied from that measurement.
 */
class LetterNamesTest extends TestCase
{
    use RefreshDatabase;

    private const STATUS = ['cor' => 'correct', 'sub' => 'substitution', 'del' => 'deletion', 'ins' => 'insertion'];

    /** @param list<array{0: ?string, 1: ?string, 2: string}> $feedback  [reference, spoken, cor|sub|del|ins] */
    private function scored(array $feedback, array $heard, float $accuracy): array
    {
        $entries = array_map(fn (array $f) => ['reference' => $f[0], 'spoken' => $f[1], 'status' => self::STATUS[$f[2]]], $feedback);
        $count = fn (string $s) => count(array_filter($entries, fn ($e) => $e['status'] === $s));

        return [
            'accuracy' => [
                'accuracy_score' => $accuracy, 'spoken_word_count' => count($heard), 'word_feedback' => $entries,
                'substitutions' => $count('substitution'), 'deletions' => $count('deletion'), 'insertions' => $count('insertion'),
            ],
            'speed' => ['wcpm' => 80, 'speed_score' => 70],
            'prosody' => ['prosody_score' => 60],
            'word_timestamps' => array_map(fn (array $h, int $i) => ['word' => $h[0], 'start' => $i, 'end' => $i + .5, 'confidence' => $h[1]], $heard, array_keys($heard)),
        ];
    }

    /** David, S A T P I N in one breath: the service wrote "as" for S (live, 2026-10-23, scored 83.33). */
    private function sAsAs(): array
    {
        return $this->scored(
            [['s', 'as', 'sub'], ['a', 'a', 'cor'], ['t', 't', 'cor'], ['p', 'p', 'cor'], ['i', 'i', 'cor'], ['n', 'n', 'cor']],
            [['as', 0.59], ['a', 1], ['t', 1], ['p', 1], ['i', 1], ['n', 1]],
            83.33
        );
    }

    /** David, M D O G C B in one breath: the service wrote "cb" for C then B (live, scored 66.67). */
    private function cbTogether(): array
    {
        return $this->scored(
            [['m', 'm', 'cor'], ['d', 'd', 'cor'], ['o', 'o', 'cor'], ['g', 'g', 'cor'], ['c', 'cb', 'sub'], ['b', null, 'del']],
            [['m', 1], ['d', 1], ['o', 1], ['g', 1], ['cb', 1]],
            66.67
        );
    }

    /** Zira, H R E F L said fast with no K: the service wrote "fl" for F then L and lost E (live, scored 33.33). */
    private function flTogether(): array
    {
        return $this->scored(
            [['h', 'h', 'cor'], ['r', 'r', 'cor'], ['e', null, 'del'], ['f', null, 'del'], ['l', null, 'del'], ['k', 'fl', 'sub']],
            [['h', 1], ['r', 1], ['fl', 0.8]],
            33.33
        );
    }

    private function activity(string $type, string $reference, ?string $purpose = 'diagnostic'): Activity
    {
        $activity = new Activity([
            'grade_level' => 'Grade 1', 'competency' => 'foundational_reading', 'activity_type' => $type, 'difficulty_tier' => 'Easy',
            'curriculum_code' => 'EN2PWS-I-2', 'reference_text' => $reference, 'passage_text' => $reference, 'purpose' => $purpose,
        ]);
        $activity->id = 0;

        return $activity;
    }

    // ------------------------------------------------------------- what it fixes (the three real cases)

    public function test_a_letter_name_written_as_another_word_is_accepted_for_that_letter(): void
    {
        $out = LetterNames::apply($this->sAsAs(), 's a t p i n');

        $this->assertSame(100.0, $out['accuracy']['accuracy_score']);
        $this->assertSame(83.33, $out['accuracy']['accuracy_score_service'], 'the service\'s own number is kept');
        $this->assertSame(['reference' => 's', 'spoken' => 's', 'status' => 'correct', 'heard' => 'as', 'accepted' => 'letter-name'], $out['accuracy']['word_feedback'][0]);
        $this->assertTrue($out['accuracy']['letters_rescored']);
        $this->assertSame(0, $out['accuracy']['substitutions']);
    }

    public function test_two_letters_written_as_one_word_are_both_accepted(): void
    {
        $out = LetterNames::apply($this->cbTogether(), 'm d o g c b');

        $this->assertSame(100.0, $out['accuracy']['accuracy_score']);
        $this->assertSame(66.67, $out['accuracy']['accuracy_score_service']);

        $feedback = $out['accuracy']['word_feedback'];
        $this->assertCount(6, $feedback);
        $this->assertSame(['reference' => 'c', 'spoken' => 'c', 'status' => 'correct', 'accepted' => 'letters-together', 'heard' => 'cb'], $feedback[4]);
        $this->assertSame(['reference' => 'b', 'spoken' => 'b', 'status' => 'correct', 'accepted' => 'letters-together', 'covered' => true], $feedback[5]);
        $this->assertSame(0, $out['accuracy']['deletions']);
    }

    public function test_a_lost_letter_is_still_a_mistake_but_the_joined_pair_is_credited(): void
    {
        // E was not heard at all and K was not said: only the pair written as "fl" is credited. 2 of 6 becomes 4 of 6.
        $out = LetterNames::apply($this->flTogether(), 'h r e f l k');

        $this->assertSame(66.67, $out['accuracy']['accuracy_score']);
        $this->assertSame(33.33, $out['accuracy']['accuracy_score_service']);
        $this->assertSame(['correct', 'correct', 'deletion', 'correct', 'correct', 'deletion'], array_column($out['accuracy']['word_feedback'], 'status'));
        $this->assertSame(['h', 'r', 'e', 'f', 'l', 'k'], array_column($out['accuracy']['word_feedback'], 'reference'));
        $this->assertSame(2, $out['accuracy']['deletions']);
    }

    // ------------------------------------------------------------- what it never does

    public function test_it_never_changes_a_reading_it_cannot_improve(): void
    {
        $perfect = $this->scored(
            [['h', 'h', 'cor'], ['r', 'r', 'cor']], [['h', 1], ['r', 1]], 100
        );
        $this->assertSame($perfect, LetterNames::apply($perfect, 'h r'));

        $wrong = $this->scored(
            [['h', 'h', 'cor'], ['r', 'dog', 'sub']], [['h', 1], ['dog', 1]], 50
        );
        $this->assertSame($wrong, LetterNames::apply($wrong, 'h r'), 'a word that is not the name of the asked letter stays a mistake');
    }

    public function test_it_only_credits_the_letters_that_were_asked_for_and_in_their_order(): void
    {
        // The same letter twice is not H then R.
        $twice = $this->scored([['h', 'hh', 'sub'], ['r', null, 'del']], [['hh', 1]], 0);
        $this->assertSame($twice, LetterNames::apply($twice, 'h r'));

        // B then C is not C then B.
        $reversed = $this->scored([['b', 'cb', 'sub'], ['c', null, 'del']], [['cb', 1]], 0);
        $this->assertSame($reversed, LetterNames::apply($reversed, 'b c'));

        // But the asked pair, in order, is.
        $pair = $this->scored([['m', 'md', 'sub'], ['d', null, 'del']], [['md', 1]], 0);
        $this->assertSame(100.0, LetterNames::apply($pair, 'm d')['accuracy']['accuracy_score']);

        // A letter that was not asked for earns nothing: "hr" for S, A.
        $other = $this->scored([['s', 'hr', 'sub'], ['a', null, 'del']], [['hr', 1]], 0);
        $this->assertSame($other, LetterNames::apply($other, 's a'));
    }

    public function test_the_live_services_answers_for_wrong_letters_are_left_exactly_as_they_were(): void
    {
        // Real answers (2026-10-23, both voices, H R E F L K asked). None of these named the letters asked, in order.
        $allWrong = $this->scored(
            [['h', 't', 'sub'], ['r', 'y', 'sub'], ['e', 'u', 'sub'], ['f', 'p', 'sub'], ['l', 'n', 'sub'], ['k', 'm', 'sub']],
            [['t', 1], ['y', 1], ['u', 1], ['p', 1], ['n', 1], ['m', 1]], 0
        );
        // All six named, in the wrong order: the service scored 0, and so must this (no coincidental letter picked out).
        $shuffled = $this->scored(
            [['h', 'k', 'sub'], ['r', 'l', 'sub'], ['e', 'f', 'sub'], ['f', 'e', 'sub'], ['l', 'r', 'sub'], ['k', 'h', 'sub']],
            [['k', 1], ['l', 1], ['f', 1], ['e', 1], ['r', 1], ['h', 1]], 0
        );
        // A for H and S for F: neither is the name of the letter asked, although "as" is a spelling of S's name elsewhere.
        $swapped = $this->scored(
            [['h', 'a', 'sub'], ['r', 'r', 'cor'], ['e', 'e', 'cor'], ['f', 's', 'sub'], ['l', 'l', 'cor'], ['k', 'k', 'cor']],
            [['a', 1], ['r', 1], ['e', 1], ['s', 1], ['l', 1], ['k', 1]], 66.67
        );
        // Only H and R said.
        $twoOnly = $this->scored(
            [['h', 'h', 'cor'], ['r', 'r', 'cor'], ['e', null, 'del'], ['f', null, 'del'], ['l', null, 'del'], ['k', null, 'del']],
            [['h', 1], ['r', 1]], 33.33
        );

        foreach ([$allWrong, $shuffled, $swapped, $twoOnly] as $answer) {
            $this->assertSame($answer, LetterNames::apply($answer, 'h r e f l k'));
        }
    }

    public function test_it_never_lowers_a_score(): void
    {
        // Whatever the heard words are, the letters named after this step are at least the letters the service named.
        $samples = [$this->sAsAs(), $this->cbTogether(), $this->flTogether()];
        $references = ['s a t p i n', 'm d o g c b', 'h r e f l k'];

        foreach ($samples as $n => $sample) {
            $before = $sample['accuracy']['accuracy_score'];
            $after = LetterNames::apply($sample, $references[$n])['accuracy']['accuracy_score'];
            $this->assertGreaterThanOrEqual($before, $after);
        }
    }

    public function test_it_applies_only_to_an_item_where_letters_are_named(): void
    {
        $this->assertTrue(LetterNames::appliesTo($this->activity('phonics', 's a t p i n')));
        $this->assertTrue(LetterNames::appliesTo($this->activity('phonics', 'h r e f l k', null)), 'a letters row in Word Train');
        $this->assertFalse(LetterNames::appliesTo($this->activity('phonics', 'cat dog')), 'words are not letters');
        $this->assertFalse(LetterNames::appliesTo($this->activity('phonics_reading', 's a t p i n')), 'only the letters item type');
        $this->assertFalse(LetterNames::appliesTo($this->activity('sentence_reading', 'a')));
        $this->assertFalse(LetterNames::appliesTo($this->activity('phonics', '')));
    }

    public function test_every_spelling_the_live_service_wrote_for_a_letter_said_alone_is_accepted(): void
    {
        // Measured 2026-10-23, both synthesized voices, each letter said alone.
        $heard = ['b' => 'be', 'e' => 'he', 'n' => 'an', 'r' => 'our', 's' => 'as', 'u' => 'you', 'y' => 'why', 'o' => 'oh'];

        foreach ($heard as $letter => $word) {
            $result = $this->scored([[$letter, $word, 'sub']], [[$word, 1]], 0);
            $this->assertSame(100.0, LetterNames::apply($result, $letter)['accuracy']['accuracy_score'], "{$word} for {$letter}");
        }
    }

    // ------------------------------------------------------------- the rest of the app still lines up

    public function test_a_joined_letter_does_not_shift_which_spoken_word_not_sure_looks_at(): void
    {
        // H R as "hr", then E, then F L as "fl", then K heard as "cake" with very low confidence: the 4th spoken word.
        $result = $this->scored(
            [['h', 'hr', 'sub'], ['r', null, 'del'], ['e', 'e', 'cor'], ['f', 'fl', 'sub'], ['l', null, 'del'], ['k', 'cake', 'sub']],
            [['hr', 1], ['e', 1], ['fl', 1], ['cake', 0.2]],
            33.33
        );

        $out = NotSure::apply(LetterNames::apply($result, 'h r e f l k'));

        $this->assertTrue($out['accuracy']['word_feedback'][5]['unsure'], 'K was heard faintly, so it is Not sure, not wrong');
        $this->assertArrayNotHasKey('unsure', $out['accuracy']['word_feedback'][1], 'the covered letter is just right');
        // 5 letters counted (K left out), all 5 right.
        $this->assertSame(100.0, $out['accuracy']['accuracy_score']);
        $this->assertSame(['right' => 5, 'notSure' => 1, 'total' => 6], NotSure::counts($out['accuracy']['word_feedback']));
    }

    // ------------------------------------------------------------- through the real client and the real check

    private function child(): Learner
    {
        return Learner::create([
            'learner_code' => LearnerCode::generate(), 'first_name' => 'Mia', 'last_name' => 'Cruz', 'grade_level' => 'Grade 1',
            'pin' => '1234', 'avatar_id' => 'A', 'mastery_level' => null, 'reading_stage' => 'starting', 'placement_answers' => null,
        ]);
    }

    public function test_the_first_check_moves_on_when_the_only_thing_wrong_was_how_the_service_wrote_the_letters(): void
    {
        config([
            'services.reading_ai.url' => 'https://reading.test', 'services.reading_ai.ready_wait' => 0,
            'services.adaptive_recommender.url' => null, 'services.activity_ai.url' => null,
        ]);

        // The child's first set is chosen by their id; make the service answer for whichever letters are shown.
        $kid = $this->child();
        $this->actingAs($kid, 'learner')->get(route('learner.diagnostic.passage'))->assertOk();
        $service = app(LearnerDiagnosticService::class);
        $shown = Activity::find($service->currentActivityId($service->diagnosticState($kid)));
        $letters = explode(' ', $shown->reference_text);

        // Everything but the last two letters named exactly; the last two written together as one word.
        $feedback = [];
        $heard = [];
        foreach (array_slice($letters, 0, 4) as $l) {
            $feedback[] = [$l, $l, 'cor'];
            $heard[] = [$l, 1];
        }
        $pair = $letters[4].$letters[5];
        $feedback[] = [$letters[4], $pair, 'sub'];
        $feedback[] = [$letters[5], null, 'del'];
        $heard[] = [$pair, 1];

        Http::fake(['reading.test/analyze' => Http::response($this->scored($feedback, $heard, 66.67))]);

        // 66.67 is under the 70 line, so before this the check ended here on the lowest step. All six were named.
        $this->post(route('learner.diagnostic.record'), ['audio' => UploadedFile::fake()->create('r.webm', 30, 'audio/webm')])
            ->assertOk()->assertSee('Keep Going');

        $this->assertSame('phonics_easy', $service->diagnosticState($kid)['current_tier']);
        $this->assertEquals(100.0, ReadingSession::where('learner_id', $kid->id)->first()->accuracy_percent);
    }

    public function test_a_letters_row_in_a_game_is_heard_when_the_letters_were_named(): void
    {
        config(['services.reading_ai.url' => 'https://reading.test', 'services.reading_ai.ready_wait' => 0]);
        Http::fake(['reading.test/analyze' => Http::response($this->cbTogether())]);

        $outcome = app(PracticeGameService::class)->checkText(
            $this->child(), ['m', 'd', 'o', 'g', 'c', 'b'], 'letters', UploadedFile::fake()->create('r.webm', 30, 'audio/webm'), app(ReadingAiClient::class)
        );

        $this->assertSame('heard', $outcome['status']);
        $this->assertSame([true, true, true, true, true, true], $outcome['matched']);
    }

    public function test_the_page_says_what_it_does(): void
    {
        $page = $this->get('/how-it-works')->assertOk()->getContent();
        $count = count(config('reading_words.letter_names'));

        $this->assertStringContainsString('Letter names.', $page);
        $this->assertStringContainsString('listed for <span class="num">'.$count.'</span> letters', $page);
        $this->assertStringContainsString('only add credit', $page);

        config(['reading_words.letter_names' => ['r' => ['are'], 's' => ['as']]]);
        $this->assertStringContainsString('listed for <span class="num">2</span> letters', $this->get('/how-it-works')->getContent());
    }
}
