<?php

namespace Tests\Feature;

use App\Models\Learner;
use App\Models\ReadingSession;
use App\Services\DiagnosticBank;
use App\Services\PracticeGameService;
use App\Services\WordBank;
use App\Support\LearnerCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The Practice Games' shared round rules live in one place, config/practice_games.php. These tests keep four things from
 * drifting apart: the website's games, the phone app (TypeScript, which cannot read the config), the starting level, the
 * "heard" share, and the public page "How TaraBasa decides" that tells people what the numbers are.
 */
class PracticeGameRulesTest extends TestCase
{
    use RefreshDatabase;

    private function child(int $rung = 3): Learner
    {
        $kid = Learner::create([
            'learner_code' => LearnerCode::generate(), 'first_name' => 'Pia', 'last_name' => 'Cruz', 'grade_level' => 'Grade 1',
            'pin' => '1234', 'avatar_id' => 'A', 'mastery_level' => 'Developing', 'reading_rung' => $rung,
        ]);

        ReadingSession::create([
            'learner_id' => $kid->id, 'activity_id' => app(DiagnosticBank::class)->idsByRung()['letters'][0],
            'session_type' => 'Diagnostic', 'initiated_by' => 'Parent', 'accuracy_percent' => 80,
        ]);
        $this->actingAs($kid, 'learner');
        config(['services.reading_ai.url' => 'https://reading.test']);

        return $kid;
    }

    // ------------------------------------------------------------- the website games read the config

    public function test_the_three_web_games_print_the_numbers_from_the_config(): void
    {
        $this->child(3);
        config(['practice_games.rounds' => 4, 'practice_games.per_round' => 5, 'practice_games.clean_hits' => 3, 'practice_games.tries' => 3]);

        $balloon = $this->get(route('learner.games.balloon-pop'))->assertOk();
        $balloon->assertSee('const MAX_ATTEMPTS = 4;', false)->assertSee('const TARGETS_PER_ROUND = 5;', false)
            ->assertSee('const CLEAN_HITS = 3;', false)->assertSee('const MAX_TRIES = 3;', false);

        $train = $this->get(route('learner.games.word-train'))->assertOk();
        $train->assertSee('var MAX_ATTEMPTS = 4;', false)->assertSee('var TARGETS = 5;', false)
            ->assertSee('var CLEAN_HITS = 3;', false)->assertSee('var MAX_TRIES = 3;', false);

        $stars = $this->get(route('learner.games.sentence-stars'))->assertOk();
        $stars->assertSee('var MAX_ATTEMPTS = 4;', false)->assertSee('var TARGETS = 5;', false)
            ->assertSee('var CLEAN_HITS = 3;', false)->assertSee('var MAX_TRIES = 3;', false);

        $syllables = $this->get(route('learner.games.syllable-steps'))->assertOk();
        $syllables->assertSee('var MAX_ATTEMPTS = 4;', false)->assertSee('var WORDS = 5;', false)
            ->assertSee('var CLEAN_HITS = 3;', false)->assertSee('var MAX_TRIES = 3;', false);
    }

    public function test_no_game_page_keeps_its_own_hard_coded_round_number(): void
    {
        $dir = base_path('resources/views/learner/games');

        foreach (['balloon-pop', 'read-game', 'syllable-steps'] as $name) {
            $source = file_get_contents("{$dir}/{$name}.blade.php");

            foreach (['MAX_ATTEMPTS', 'TARGETS_PER_ROUND', 'TARGETS', 'WORDS', 'CLEAN_HITS', 'MAX_TRIES'] as $constant) {
                $this->assertDoesNotMatchRegularExpression(
                    '/\b(?:const|var)\s+'.$constant.'\s*=\s*\d/',
                    $source,
                    "{$name} still has a literal number for {$constant}; it must print config('practice_games.*')"
                );
            }
        }
    }

    // ------------------------------------------------------------- the phone app has the same numbers

    public function test_the_phone_games_use_the_same_numbers_as_the_config(): void
    {
        $expected = [
            'MAX_ATTEMPTS' => config('practice_games.rounds'),
            'CLEAN_HITS' => config('practice_games.clean_hits'),
            'MAX_TRIES' => config('practice_games.tries'),
        ];
        $perRound = config('practice_games.per_round');

        $files = [
            'ReadGame.tsx' => ['TARGETS' => $perRound],
            'BalloonPop.tsx' => ['TARGETS' => $perRound],
            'SyllableSteps.tsx' => ['WORDS' => $perRound],
        ];

        foreach ($files as $file => $extra) {
            $path = base_path('mobile/src/'.$file);
            $this->assertFileExists($path);
            $source = file_get_contents($path);

            foreach ($expected + $extra as $constant => $value) {
                $this->assertSame(1, preg_match('/^const '.$constant.' = (\d+);/m', $source, $m), "{$file} has no `const {$constant} = <number>;`");
                $this->assertSame((int) $value, (int) $m[1], "{$file}: {$constant} is {$m[1]} but config/practice_games.php says {$value}");
            }
        }
    }

    // ------------------------------------------------------------- the starting level

    public function test_the_starting_level_follows_the_two_steps_in_the_config(): void
    {
        $game = app(PracticeGameService::class);
        $at = fn (int $rung) => $game->startLevel(Learner::create([
            'learner_code' => LearnerCode::generate(), 'first_name' => 'K'.$rung, 'last_name' => 'Cruz', 'grade_level' => 'Grade 1',
            'pin' => '1234', 'avatar_id' => 'A', 'mastery_level' => 'Developing', 'reading_rung' => $rung,
        ]));

        // As shipped: steps 0 and 1 start at level 1, steps 2 and 3 at level 2, step 4 and up at level 3.
        $this->assertSame([1, 1, 2, 2, 3, 3, 3], array_map($at, [0, 1, 2, 3, 4, 5, 6]));

        // Move both lines and the starting levels move with them.
        config(['practice_games.level_2_from_step' => 1, 'practice_games.level_3_from_step' => 3]);
        $this->assertSame([1, 2, 2, 3, 3, 3, 3], array_map($at, [0, 1, 2, 3, 4, 5, 6]));
    }

    // ------------------------------------------------------------- the "heard" share

    public function test_the_heard_share_in_the_config_decides_when_a_row_counts_as_read(): void
    {
        $this->child();

        // Three words asked, only the first and the last heard: two of three is 67 percent.
        Http::fake(['reading.test/analyze' => Http::response([
            'accuracy' => ['accuracy_score' => 67, 'spoken_word_count' => 3, 'substitutions' => 1, 'deletions' => 0, 'insertions' => 0, 'word_feedback' => [
                ['reference' => 'cat', 'spoken' => 'cat', 'status' => 'correct'],
                ['reference' => 'dog', 'spoken' => 'dig', 'status' => 'substitution'],
                ['reference' => 'pig', 'spoken' => 'pig', 'status' => 'correct'],
            ]],
            'speed' => ['wcpm' => 40], 'prosody' => ['prosody_score' => 50],
            'word_timestamps' => [
                ['word' => 'cat', 'start' => 0, 'end' => 0.5, 'confidence' => 0.9],
                ['word' => 'dig', 'start' => 1, 'end' => 1.5, 'confidence' => 0.9],
                ['word' => 'pig', 'start' => 2, 'end' => 2.5, 'confidence' => 0.9],
            ],
        ])]);
        $clip = fn () => UploadedFile::fake()->create('row.webm', 20, 'audio/webm');

        config(['practice_games.heard_share' => 0.66]);
        $this->postJson(route('learner.games.check-text'), ['text' => 'cat dog pig', 'kind' => 'row', 'audio' => $clip()])
            ->assertOk()->assertJsonPath('status', 'heard');

        config(['practice_games.heard_share' => 0.9]);
        $this->postJson(route('learner.games.check-text'), ['text' => 'cat dog pig', 'kind' => 'row', 'audio' => $clip()])
            ->assertOk()->assertJsonPath('status', 'again');
    }

    // ------------------------------------------------------------- the public page

    public function test_the_public_page_describes_the_games_and_the_word_bank_from_the_same_numbers(): void
    {
        $page = $this->get(route('how-it-works'))->assertOk();

        $page->assertSee('12. Practice games and the word bank', false)->assertSee('id="games"', false)
            ->assertSee('13. What it does not do', false)->assertSee('14. Checking the numbers', false)
            ->assertSee('15. The outside services', false)->assertSee('16. Sources', false);

        // The numbers shown are the ones the games and the bank run on.
        $this->assertSame(3, WordBank::MASTERED_AFTER);
        $this->assertSame(4, WordBank::MAX_NEW_PER_READING);
        $page->assertSee('<span class="num">3</span> things to read', false);
        $page->assertSee('66 percent', false)->assertSee('up to <span class="num">2</span> tries', false);
        $page->assertSee('at most <span class="num">4</span> new words', false)
            ->assertSee('<span class="num">3</span> correct readings', false);
        $page->assertSee('level 1 for the first 2 steps (letters, words), level 2 from short sentences, and level 3 from short stories', false);
        $page->assertSee('never changes points, streak or level', false)->assertSee('No real child has played the games yet', false);
    }

    public function test_the_public_page_follows_the_config_not_a_copy_of_it(): void
    {
        config([
            'practice_games.per_round' => 4, 'practice_games.tries' => 3, 'practice_games.rounds' => 5, 'practice_games.clean_hits' => 3,
            'practice_games.heard_share' => 0.75, 'practice_games.level_2_from_step' => 3, 'practice_games.level_3_from_step' => 5,
        ]);

        $this->get(route('how-it-works'))->assertOk()
            ->assertSee('A round has <span class="num">4</span> things to read', false)
            ->assertSee('up to <span class="num">3</span> tries', false)
            ->assertSee('<span class="num">3</span> or more were read right the first time', false)
            ->assertSee('up to <span class="num">5</span> rounds', false)
            ->assertSee('<span class="num">75 percent</span>', false)
            ->assertSee('level 1 for the first 3 steps (letters, words, short sentences), level 2 from sentences, and level 3 from stories', false);
    }

    public function test_the_page_keeps_to_the_copy_rules_in_the_new_section(): void
    {
        $html = $this->get(route('how-it-works'))->assertOk()->getContent();
        $start = strpos($html, '<h2 id="games">');
        $end = strpos($html, '<h2 id="never">');
        $section = strip_tags(substr($html, $start, $end - $start));

        $this->assertDoesNotMatchRegularExpression('/[\x{2014}\x{2013}]/u', $section, 'no dashes in the copy');
        $this->assertDoesNotMatchRegularExpression('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $section, 'no emoji in the copy');
    }
}
