<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\GamePlay;
use App\Models\Learner;
use App\Models\PersonalWordBank;
use App\Models\ReadingSession;
use App\Services\DiagnosticBank;
use App\Services\PracticeGameService;
use App\Support\LearnerCode;
use App\Support\SyllableMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Syllable Steps: the syllable method (config/syllable_method.php) as a Practice Game. Four steps in the method's order (hear and
 * echo, split, join, read in a sentence), only the sentence ever scored, free play, and not offered to a child still on the Letters
 * step (they have no word level yet). These pin the rules that sit under the screen; the screen itself was also played in a browser.
 */
class SyllableStepsGameTest extends TestCase
{
    use RefreshDatabase;

    private function child(int $rung = 3, array $o = []): Learner
    {
        $kid = Learner::create($o + [
            'learner_code' => LearnerCode::generate(), 'first_name' => 'Pia', 'last_name' => 'Cruz', 'grade_level' => 'Grade 1',
            'pin' => '1234', 'avatar_id' => 'A', 'mastery_level' => 'Developing', 'reading_rung' => $rung, 'points' => 40, 'streak' => 3,
        ]);

        // A finished first check, which the games are behind.
        ReadingSession::create([
            'learner_id' => $kid->id, 'activity_id' => app(DiagnosticBank::class)->idsByRung()['letters'][0],
            'session_type' => 'Diagnostic', 'initiated_by' => 'Parent', 'accuracy_percent' => 80,
        ]);
        $this->actingAs($kid, 'learner');
        config(['services.reading_ai.url' => 'https://reading.test']);

        return $kid;
    }

    /** Three practice readings in which the child reads longer words in pieces (their main kind of mistake). */
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

    // ------------------------------------------------------------- who is offered it

    public function test_the_level_follows_the_childs_step_and_a_child_on_letters_has_none(): void
    {
        $game = app(PracticeGameService::class);

        // The method's own levels: CVC + CVC for steps 1 and 2, CVC + CVCe for 3 and 4, CVC + VC for 5 and 6.
        foreach ([[1, 1], [2, 1], [3, 2], [4, 2], [5, 3], [6, 3]] as [$rung, $level]) {
            $kid = Learner::create([
                'learner_code' => LearnerCode::generate(), 'first_name' => 'K'.$rung, 'last_name' => 'Cruz', 'grade_level' => 'Grade 1',
                'pin' => '1234', 'avatar_id' => 'A', 'mastery_level' => 'Developing', 'reading_rung' => $rung,
            ]);
            $this->assertSame($level, $game->syllableStartLevel($kid), "step {$rung}");
        }

        $letters = Learner::create([
            'learner_code' => LearnerCode::generate(), 'first_name' => 'Lea', 'last_name' => 'Cruz', 'grade_level' => 'Grade 1',
            'pin' => '1234', 'avatar_id' => 'A', 'mastery_level' => 'Beginning', 'reading_rung' => 0,
        ]);
        $this->assertNull($game->syllableStartLevel($letters));
    }

    public function test_the_hub_shows_the_card_to_a_child_with_a_word_level_and_the_page_opens(): void
    {
        $this->child(3);

        $this->get(route('learner.games.index'))->assertOk()
            ->assertSee('Syllable Steps')->assertSee('Clap the parts of a longer word, tap them together, then read it in a sentence.')
            ->assertSee(route('learner.games.syllable-steps'), false)
            // Still free play, and the other three games are unchanged.
            ->assertSee('Balloon Pop')->assertSee('Word Train')->assertSee('Sentence Stars')
            ->assertSee('what you read in a game is not graded');

        $this->get(route('learner.games.syllable-steps'))->assertOk();
    }

    public function test_a_child_still_on_the_letters_step_is_not_offered_it_and_is_sent_back_to_the_games(): void
    {
        $this->child(0, ['mastery_level' => 'Beginning']);

        $this->get(route('learner.games.index'))->assertOk()
            ->assertDontSee('Syllable Steps')->assertDontSee(route('learner.games.syllable-steps'), false)
            ->assertSee('Word Train');

        // A saved link or a typed address lands on the Games page, never on an error.
        $this->get(route('learner.games.syllable-steps'))->assertRedirect(route('learner.games.index'));
    }

    public function test_a_child_who_has_not_done_the_first_check_cannot_open_it(): void
    {
        $kid = Learner::create([
            'learner_code' => LearnerCode::generate(), 'first_name' => 'New', 'last_name' => 'Cruz', 'grade_level' => 'Grade 1',
            'pin' => '1234', 'avatar_id' => 'A', 'mastery_level' => 'Developing', 'reading_rung' => 3,
        ]);
        $this->actingAs($kid, 'learner');

        // The games sit behind the first reading check, like the other three.
        $this->get(route('learner.games.syllable-steps'))->assertRedirect();
    }

    public function test_it_is_only_for_a_signed_in_child(): void
    {
        $this->get(route('learner.games.syllable-steps'))->assertRedirect();
    }

    // ------------------------------------------------------------- what it offers

    public function test_every_word_has_its_parts_and_a_sentence_with_the_word_in_it_at_every_level(): void
    {
        $kid = $this->child(5);
        $levels = app(PracticeGameService::class)->syllableLevels($kid);

        $this->assertSame([1, 2, 3], array_keys($levels));
        foreach ($levels as $level => $pools) {
            $this->assertNotEmpty($pools['level'], "level {$level} has words of its own");
            foreach (array_merge($pools['own'], $pools['level'], $pools['review']) as $w) {
                $this->assertGreaterThanOrEqual(2, count($w['parts']), $w['word']);
                $this->assertSame($w['word'], implode('', $w['parts']), $w['word']);
                // The sentence is where the word is read (step 4), and the page picks the word out of it, so it must be there whole.
                $this->assertContains($w['word'], \App\Services\PracticeGameService::wordsOf($w['sentence']), $w['word']);
            }
        }

        // The level's own words are the method's words of that level; easier levels are kept only for review.
        $this->assertSame(array_column(SyllableMethod::words(1), 'word'), array_column($levels[1]['level'], 'word'));
        $this->assertSame([], $levels[1]['review']);
        $this->assertNotEmpty($levels[3]['review']);
        foreach ($levels[2]['review'] as $w) {
            $this->assertSame(1, SyllableMethod::find($w['word'])['level']);
        }
    }

    public function test_a_word_the_child_has_been_missing_comes_first_but_only_from_its_own_level_up(): void
    {
        $kid = $this->child(3);
        foreach (['pancake', 'lemon', 'cat'] as $word) {
            PersonalWordBank::create(['learner_id' => $kid->id, 'word' => $word, 'mastery_status' => 'Struggling']);
        }

        $levels = app(PracticeGameService::class)->syllableLevels($kid);

        // "cat" is not one of the method's words, so it never appears here (Balloon Pop uses it); "pancake" is level 2, "lemon" level 3.
        $this->assertSame([], array_column($levels[1]['own'], 'word'));
        $this->assertSame(['pancake'], array_column($levels[2]['own'], 'word'));
        $this->assertEqualsCanonicalizing(['pancake', 'lemon'], array_column($levels[3]['own'], 'word'));
    }

    public function test_the_page_carries_the_childs_start_level_and_the_methods_drill_count(): void
    {
        $this->child(3);
        $this->get(route('learner.games.syllable-steps'))->assertOk()
            ->assertSee('var level = 2;', false)
            ->assertSee('var REPEAT = '.config('syllable_method.repeat').';', false)
            ->assertSee('"word":"pancake"', false);

        $this->child(5, ['first_name' => 'Rafa']);
        $this->get(route('learner.games.syllable-steps'))->assertOk()->assertSee('var level = 3;', false);
    }

    // ------------------------------------------------------------- the screen: taps, a spoken instruction, one scored step

    public function test_the_four_steps_are_there_in_the_methods_order_and_only_the_last_listens(): void
    {
        $this->child(3);

        $page = $this->get(route('learner.games.syllable-steps'))->assertOk();
        $page->assertSee("var STEP_NAMES = ['Listen', 'Split', 'Join', 'Read'];", false)
            ->assertSee('Say it with Tara')->assertSee('Clap the parts')->assertSee('Join the parts')->assertSee('Read it in a sentence')
            ->assertSee('Only this step listens')
            // Only the whole sentence goes to the speech check, as every other game does: never a part of a word.
            ->assertSee("window.tarabasaListenForText(wordsOf(cur.sentence).join(' '), 'sentence'", false)
            ->assertSee('There is no wrong answer here.');
    }

    public function test_joining_is_by_tapping_in_order_and_never_by_sliding_or_dragging(): void
    {
        $this->child(3);

        $page = $this->get(route('learner.games.syllable-steps'))->assertOk();
        // One finger taps: small children manage a tap far more reliably than a slide or a drag (see CLAUDE.md).
        $page->assertSee('data-act="jtap"', false)->assertSee('Tap the first part. Then tap the next part.')
            ->assertSee('Start with the first part.')->assertSee('Now tap the next part.')
            ->assertDontSee('type="range"', false)->assertDontSee('draggable', false);
    }

    public function test_tara_says_every_instruction_and_a_speaker_says_it_again_and_the_next_tap_glows(): void
    {
        $this->child(3);

        $page = $this->get(route('learner.games.syllable-steps'))->assertOk();
        $page->assertSee('id="spkBtn"', false)->assertSee('Hear Tara say it again')
            ->assertSee('function speakInstruction()', false)->assertSee('instrTimer = setTimeout(speakInstruction, 350)', false)
            // The glow, and a still outline for a child who has asked for less motion.
            ->assertSee('@keyframes cueGlow', false)->assertSee('prefers-reduced-motion:reduce', false)
            // The browser only lets a page speak after a tap, so the first instruction waits for the speaker, which glows.
            ->assertSee('if (!userActive && window.tbCanSpeak) c = spkBtn;', false);
    }

    public function test_the_page_uses_the_shared_voice_sounds_animations_and_report(): void
    {
        $this->child(3);

        $page = $this->get(route('learner.games.syllable-steps'))->assertOk();
        $page->assertSee('vendor/lottie-web-5.12.2.min.js', false)->assertSee("'tara-owl-head'", false)->assertSee("'star-happy'", false)
            ->assertSee('role="img" aria-label="Tara the owl, listening"', false)
            ->assertSee('tarabasaPlaySfx', false)->assertSee('tarabasaReportGame', false)
            // The finished game reports to its own address (written into the page as JSON, so the slashes are escaped).
            ->assertSee(json_encode(route('learner.games.finish', 'syllable-steps')), false)
            ->assertSee('I read it!');
    }

    // ------------------------------------------------------------- it is free play, and the sentence check is the usual one

    public function test_a_finished_game_is_recorded_for_the_games_badges_and_nothing_else_changes(): void
    {
        $kid = $this->child(3);

        $this->postJson(route('learner.games.finish', 'syllable-steps'), [
            'level_reached' => 2, 'top_level_cleared' => false, 'had_perfect_round' => true, 'rounds' => 3,
        ])->assertOk()->assertJsonStructure(['newBadges']);

        $this->assertSame(1, GamePlay::where('learner_id', $kid->id)->where('game', 'syllable-steps')->count());

        $fresh = $kid->fresh();
        $this->assertSame(40, (int) $fresh->points);
        $this->assertSame(3, (int) $fresh->streak);
        $this->assertSame('Developing', $fresh->mastery_level);
        $this->assertSame(3, (int) $fresh->reading_rung);
    }

    public function test_a_play_counts_toward_the_general_games_badges(): void
    {
        $kid = $this->child(3);

        foreach (['balloon-pop', 'syllable-steps'] as $game) {
            $this->postJson(route('learner.games.finish', $game), ['level_reached' => 1, 'top_level_cleared' => false, 'had_perfect_round' => false, 'rounds' => 1])->assertOk();
        }

        $codes = \App\Models\LearnerBadge::where('learner_id', $kid->id)->pluck('badge_code')->all();
        // Two different games on one day, whichever two they are.
        $this->assertContains('game_practice_party', $codes);
    }

    public function test_the_sentence_is_checked_by_the_same_service_as_sentence_stars_and_nothing_is_kept(): void
    {
        $kid = $this->child(3);
        $feedback = array_map(fn (string $w) => ['reference' => $w, 'spoken' => $w, 'status' => 'correct'], ['we', 'watch', 'the', 'sunset']);
        Http::fake(['reading.test/analyze' => Http::response([
            'accuracy' => ['accuracy_score' => 100, 'spoken_word_count' => 4, 'substitutions' => 0, 'deletions' => 0, 'insertions' => 0, 'word_feedback' => $feedback],
            'speed' => ['wcpm' => 40], 'prosody' => ['prosody_score' => 50],
            'word_timestamps' => array_map(fn (string $w) => ['word' => $w, 'start' => 0, 'end' => 0.5, 'confidence' => 0.9], ['we', 'watch', 'the', 'sunset']),
        ])]);
        $before = ReadingSession::count();

        $this->postJson(route('learner.games.check-text'), [
            'text' => 'we watch the sunset', 'kind' => 'sentence', 'audio' => UploadedFile::fake()->create('r.webm', 20, 'audio/webm'),
        ])->assertOk()->assertJson(['status' => 'heard', 'matched' => [true, true, true, true]]);

        // Free play: no reading session, no word bank entry, no points.
        $this->assertSame($before, ReadingSession::count());
        $this->assertSame(0, PersonalWordBank::where('learner_id', $kid->id)->count());
    }

    // ------------------------------------------------------------- the recommendation

    public function test_a_child_who_reads_longer_words_in_pieces_is_pointed_to_it(): void
    {
        $kid = $this->child(3);
        $this->piecesMistakes($kid);

        $this->assertSame(['game' => 'syllable-steps', 'reason' => 'long-words'], $kid->recommendedGameFocus());
        $this->get(route('learner.games.index'))->assertOk()
            ->assertSee('Longer words are easier in parts. Syllable Steps cuts them up, then puts them back together!');
    }

    // ------------------------------------------------------------- the phone app's API

    public function test_a_phone_build_that_does_not_ask_for_it_is_never_told_to_lead_with_the_game(): void
    {
        $kid = $this->child(3);
        $this->piecesMistakes($kid);
        $token = $kid->createToken('phone')->plainTextToken;

        // An older build (0.5.3 and before) has no Syllable Steps: it does not ask, so the game never leads for it.
        $old = $this->withToken($token)->getJson('/api/learner/games')->assertOk();
        $this->assertNotSame('syllable-steps', $old->json('recommended'));
        $this->assertNotSame('syllable-steps', $kid->recommendedGameFocus(false)['game']);
        // The extra field is there but an older build ignores it.
        $this->assertTrue($old->json('syllableSteps'));

        // A build that has the game asks with ?syllables=1 and is pointed to it, with the reason.
        $new = $this->withToken($token)->getJson('/api/learner/games?syllables=1')->assertOk();
        $this->assertSame('syllable-steps', $new->json('recommended'));
        $this->assertSame('long-words', $new->json('reason'));
    }

    public function test_the_phone_gets_the_levels_the_drill_count_and_the_start_level(): void
    {
        $kid = $this->child(3);
        PersonalWordBank::create(['learner_id' => $kid->id, 'word' => 'pancake', 'mastery_status' => 'Struggling']);

        $res = $this->withToken($kid->createToken('phone')->plainTextToken)->getJson('/api/learner/games/syllables')->assertOk();

        $this->assertSame(2, $res->json('startLevel'));
        $this->assertSame((int) config('syllable_method.repeat'), $res->json('repeat'));
        $this->assertSame(['1', '2', '3'], array_map('strval', array_keys($res->json('levels'))));
        // The same data the website page gets: parts that join into the word, a sentence with the word in it, the child's own word first.
        $this->assertSame('pancake', $res->json('levels.2.own.0.word'));
        $this->assertSame(['pan', 'cake'], $res->json('levels.2.own.0.parts'));
        $this->assertSame('Mom makes a pancake.', $res->json('levels.2.own.0.sentence'));
        $this->assertNotEmpty($res->json('levels.3.review'));
    }

    public function test_a_child_on_letters_is_not_offered_it_on_the_phone_either(): void
    {
        $kid = $this->child(0, ['mastery_level' => 'Beginning']);
        $token = $kid->createToken('phone')->plainTextToken;

        $this->withToken($token)->getJson('/api/learner/games?syllables=1')->assertOk()->assertJson(['syllableSteps' => false]);
        $this->withToken($token)->getJson('/api/learner/games/syllables')->assertNotFound();
    }

    public function test_a_phone_game_report_for_it_is_recorded_like_the_other_games(): void
    {
        $kid = $this->child(3);

        $this->withToken($kid->createToken('phone')->plainTextToken)
            ->postJson('/api/learner/games/syllable-steps/finish', ['level_reached' => 2, 'top_level_cleared' => false, 'had_perfect_round' => false, 'rounds' => 2])
            ->assertOk()->assertJsonStructure(['newBadges']);

        $this->assertSame(1, GamePlay::where('learner_id', $kid->id)->where('game', 'syllable-steps')->count());
    }

    public function test_a_child_with_no_such_pattern_is_pointed_where_they_were_before(): void
    {
        $kid = $this->child(3);

        $this->assertSame('sentence-stars', $kid->recommendedGameFocus()['game']);

        PersonalWordBank::create(['learner_id' => $kid->id, 'word' => 'hen', 'mastery_status' => 'Struggling']);
        $this->assertSame('balloon-pop', $kid->recommendedGameFocus()['game']);
    }

    public function test_a_child_on_letters_is_still_pointed_to_word_train(): void
    {
        $kid = $this->child(0, ['mastery_level' => 'Beginning']);
        $this->piecesMistakes($kid);

        $this->assertSame(['game' => 'word-train', 'reason' => 'letters'], $kid->recommendedGameFocus());
    }
}
