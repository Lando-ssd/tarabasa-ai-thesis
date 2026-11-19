<?php

namespace Tests\Feature;

use App\Models\GamePlay;
use App\Models\Learner;
use App\Models\ReadingSession;
use App\Services\DiagnosticBank;
use App\Support\LearnerCode;
use App\Support\SoundHunt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Sound Hunt (config/sound_hunt.php): the listen and tap game with no microphone. These pin what sits under the screen: that the word lists are
 * what they say they are (a rhyme family really ends the same, a first sound group really starts the same), that every question has one clear
 * answer, which level a child starts at, whether the cards show the written word, what the Games page says about it, the voice clips, and that a
 * finished game is reported like any other. The screen itself was also played in a browser.
 */
class SoundHuntGameTest extends TestCase
{
    use RefreshDatabase;

    private function child(?int $rung = 3, array $o = []): Learner
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

        return $kid;
    }

    // ------------------------------------------------------------------------------------------------------------------- the word lists

    public function test_every_group_holds_what_it_says_it_holds(): void
    {
        foreach (config('sound_hunt.rhymes') as $family => $words) {
            $this->assertGreaterThanOrEqual(3, count($words), "family $family is too small");
            foreach ($words as $word) {
                $this->assertStringEndsWith($family, $word, "$word is not in the -$family family");
            }
        }
        foreach (config('sound_hunt.first') as $letter => $words) {
            $this->assertGreaterThanOrEqual(4, count($words), "first sound group $letter is too small");
            foreach ($words as $word) {
                $this->assertStringStartsWith($letter, $word, "$word does not start with $letter");
            }
        }
        foreach (config('sound_hunt.last') as $letter => $words) {
            $this->assertGreaterThanOrEqual(3, count($words), "last sound group $letter is too small");
            foreach ($words as $word) {
                $this->assertStringEndsWith($letter, $word, "$word does not end with $letter");
            }
        }
    }

    public function test_a_word_is_in_one_group_only_and_is_plain_lowercase(): void
    {
        foreach (['rhymes', 'first', 'last'] as $set) {
            $seen = [];
            foreach (config("sound_hunt.$set") as $words) {
                foreach ($words as $word) {
                    $this->assertMatchesRegularExpression('/^[a-z]{2,5}$/', $word);
                    $this->assertArrayNotHasKey($word, $seen, "$word is in two $set groups");
                    $seen[$word] = true;
                }
            }
        }
    }

    public function test_no_two_first_sound_groups_share_a_sound(): void
    {
        // c is always the hard sound in these words, so no k group may exist next to it; g is always the hard sound too.
        $letters = array_keys(config('sound_hunt.first'));
        $this->assertNotContains('k', $letters);
        $this->assertNotContains('q', $letters);
        $this->assertNotContains('x', $letters);
        foreach (config('sound_hunt.first.c') as $word) {
            $this->assertDoesNotMatchRegularExpression('/^c[ei]/', $word, "$word has a soft c");
        }
        foreach (config('sound_hunt.first.g') as $word) {
            $this->assertDoesNotMatchRegularExpression('/^g[ei]n/', $word, "$word may have a soft g");
        }
    }

    public function test_the_copy_has_no_dashes_and_no_emoji(): void
    {
        $text = json_encode(config('sound_hunt.phrases'), JSON_UNESCAPED_UNICODE).json_encode(config('sound_hunt.levels'), JSON_UNESCAPED_UNICODE);
        $this->assertDoesNotMatchRegularExpression('/[\x{2013}\x{2014}]/u', $text);
        $this->assertDoesNotMatchRegularExpression('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $text);
    }

    // ------------------------------------------------------------------------------------------------------------------- the questions

    public function test_every_question_has_one_clear_answer(): void
    {
        $family = [];
        foreach (config('sound_hunt.rhymes') as $key => $words) {
            foreach ($words as $w) {
                $family[$w] = $key;
            }
        }

        foreach ([1, 2, 3] as $level) {
            for ($seed = 1; $seed <= 25; $seed++) {
                mt_srand($seed);
                $pool = SoundHunt::questions($level);
                $this->assertCount((int) config('sound_hunt.pool'), $pool, "level $level seed $seed");
                $targets = array_column($pool, 'target');
                $this->assertSame($targets, array_values(array_unique($targets)), 'a target came twice');

                foreach ($pool as $q) {
                    $this->assertCount((int) config('sound_hunt.choices'), $q['choices']);
                    $this->assertSame($q['choices'], array_values(array_unique($q['choices'])));
                    $this->assertContains($q['answer'], $q['choices']);
                    $this->assertNotContains($q['target'], $q['choices']);
                    $others = array_values(array_diff($q['choices'], [$q['answer']]));

                    if ($level === 1) {
                        $this->assertSame($family[$q['target']], $family[$q['answer']]);
                        foreach ($others as $w) {
                            $this->assertNotSame($family[$q['target']], $family[$w] ?? null, "$w rhymes with {$q['target']}");
                            $this->assertNotSame($q['target'][0], $w[0], "$w starts like the target");
                            $this->assertNotSame($q['answer'][0], $w[0], "$w starts like the answer");
                        }
                        $this->assertSame(strlen($family[$q['target']]), $q['mark']);
                    } elseif ($level === 2) {
                        $this->assertSame($q['target'][0], $q['answer'][0]);
                        foreach ($others as $w) {
                            $this->assertNotSame($q['target'][0], $w[0], "$w starts like {$q['target']}");
                            $this->assertFalse(isset($family[$w], $family[$q['target']]) && $family[$w] === $family[$q['target']], "$w rhymes with {$q['target']}");
                        }
                        $this->assertSame(1, $q['mark']);
                    } else {
                        $this->assertSame(substr($q['target'], -1), substr($q['answer'], -1));
                        foreach ($others as $w) {
                            $this->assertNotSame(substr($q['target'], -1), substr($w, -1), "$w ends like {$q['target']}");
                        }
                    }
                }
            }
        }
    }

    public function test_the_childs_own_missed_words_are_asked_about_first(): void
    {
        mt_srand(7);
        $this->assertSame('pig', SoundHunt::questions(1, ['pig'])[0]['target']);
        $this->assertSame('hen', SoundHunt::questions(2, ['Hen', 'zzz'])[0]['target']);
        $this->assertSame('dog', SoundHunt::questions(3, ['dog'])[0]['target']);

        // A word that has no group at this level is ignored (there is nothing to match it with).
        mt_srand(7);
        $this->assertNotSame('apple', SoundHunt::questions(1, ['apple'])[0]['target']);
    }

    public function test_what_tara_says_is_made_of_clips_that_exist_in_the_list(): void
    {
        $keys = array_keys(SoundHunt::clipKeys());
        foreach ([1, 2, 3] as $level) {
            mt_srand($level);
            foreach (SoundHunt::questions($level) as $q) {
                foreach (['ask', 'again', 'right', 'reveal'] as $line) {
                    $this->assertNotSame([], $q['say'][$line]['parts']);
                    $this->assertNotSame('', $q['say'][$line]['text']);
                    foreach ($q['say'][$line]['parts'] as $part) {
                        $this->assertContains($part, $keys, "no clip called $part");
                    }
                }
            }
        }

        mt_srand(3);
        $q = SoundHunt::questions(1)[0];
        $this->assertSame("Which one rhymes with {$q['target']}?", $q['say']['ask']['text']);
        $this->assertStringContainsString('rhyme.', $q['say']['right']['text']);
        $this->assertStringStartsWith("Let's listen again.", $q['say']['again']['text']);
        $this->assertStringEndsWith("Let's try the next one.", $q['say']['reveal']['text']);
    }

    public function test_every_word_gets_one_clip_and_there_are_no_duplicates(): void
    {
        $keys = SoundHunt::clipKeys();
        $words = SoundHunt::words();
        $this->assertSame($words, array_values(array_unique($words)));
        foreach ($words as $word) {
            $this->assertSame($word, $keys['w-'.$word]);
        }
        $this->assertCount(count($words) + count(config('sound_hunt.phrases')), $keys);
    }

    // ------------------------------------------------------------------------------------------------------------------- the level and the cards

    public function test_the_start_level_follows_the_step_and_the_usual_mistake(): void
    {
        $this->assertSame(1, SoundHunt::startLevel(null));
        $this->assertSame(1, SoundHunt::startLevel(0));
        $this->assertSame(2, SoundHunt::startLevel(1));
        $this->assertSame(3, SoundHunt::startLevel(2));
        $this->assertSame(3, SoundHunt::startLevel(6));

        // The kind of mistake overrides the step: first sounds, last sounds, and the middle sound (which rhymes work on).
        $this->assertSame(2, SoundHunt::startLevel(0, 'beginning'));
        $this->assertSame(3, SoundHunt::startLevel(1, 'ending_sound'));
        $this->assertSame(1, SoundHunt::startLevel(5, 'vowels'));
        $this->assertSame(1, SoundHunt::startLevel(0, 'endings'));
    }

    public function test_only_a_child_who_has_met_words_is_shown_the_written_word(): void
    {
        $this->assertFalse(SoundHunt::readsWords(null));
        $this->assertFalse(SoundHunt::readsWords(0));
        $this->assertTrue(SoundHunt::readsWords(1));
        $this->assertTrue(SoundHunt::readsWords(4));
    }

    // ------------------------------------------------------------------------------------------------------------------- the page

    public function test_the_page_is_behind_the_sign_in(): void
    {
        $this->get(route('learner.games.sound-hunt'))->assertRedirect();
    }

    public function test_the_page_opens_with_no_microphone_and_the_right_cards(): void
    {
        $this->child(0);
        $html = $this->get(route('learner.games.sound-hunt'))->assertOk()->getContent();

        $this->assertStringContainsString('Sound Hunt', $html);
        $this->assertStringContainsString('var READS = false;', $html);
        $this->assertStringContainsString('var CLIPS = [];', $html);
        // Nothing records or sends anything: it is a listening game.
        $this->assertStringNotContainsString('getUserMedia', $html);
        $this->assertStringNotContainsString('tarabasaListenForText', $html);
        $this->assertStringNotContainsString('check-text', $html);
        $this->assertStringContainsString(json_encode(route('learner.games.finish', 'sound-hunt')), $html);
        $this->assertStringContainsString('"kind":"rhyme"', $html);
        $this->assertStringContainsString('"kind":"last"', $html);

        $this->child(3);
        $this->get(route('learner.games.sound-hunt'))->assertOk()->assertSee('var READS = true;', false);
    }

    public function test_the_page_starts_at_the_level_the_step_gives(): void
    {
        $this->child(1);
        $this->get(route('learner.games.sound-hunt'))->assertViewHas('startLevel', 2)->assertViewHas('readsWords', true);

        $this->child(0);
        $this->get(route('learner.games.sound-hunt'))->assertViewHas('startLevel', 1)->assertViewHas('readsWords', false);
    }

    public function test_the_page_offers_the_levels_questions(): void
    {
        $this->child(2);
        $levels = $this->get(route('learner.games.sound-hunt'))->assertOk()->viewData('levels');

        $this->assertSame([1, 2, 3], array_keys($levels));
        foreach ($levels as $questions) {
            $this->assertCount((int) config('sound_hunt.pool'), $questions);
        }
    }

    // ------------------------------------------------------------------------------------------------------------------- the Games page

    public function test_the_games_page_has_the_card_and_recommends_it_to_a_child_on_the_letters_step(): void
    {
        $this->child(0);
        $page = $this->get(route('learner.games.index'))->assertOk();
        $page->assertSee('Sound Hunt');
        $page->assertSee(route('learner.games.sound-hunt'), false);
        $this->assertSame(['game' => 'sound-hunt', 'reason' => 'sounds'], $page->viewData('recommendedGame'));
        $page->assertSee('Sound Hunt helps you hear the sounds in words');
    }

    public function test_the_games_page_says_good_for_you_on_the_first_words_step_and_new_above_it(): void
    {
        $this->child(1);
        $this->get(route('learner.games.index'))->assertOk()->assertViewHas('goodForYou', true)->assertSee('Good for you');

        $this->child(4);
        $this->get(route('learner.games.index'))->assertOk()->assertViewHas('goodForYou', false)->assertDontSee('Good for you');
    }

    public function test_only_a_caller_that_asks_is_told_to_lead_with_sound_hunt(): void
    {
        $kid = $this->child(0);
        // An older phone build never asks, so it keeps the answer it always had.
        $this->assertSame(['game' => 'word-train', 'reason' => 'letters'], $kid->recommendedGameFocus());
        $this->assertSame(['game' => 'sound-hunt', 'reason' => 'sounds'], $kid->recommendedGameFocus(true, true));

        // Above the letters step nothing changes.
        $kid = $this->child(3);
        $this->assertSame($kid->recommendedGameFocus()['game'], $kid->recommendedGameFocus(true, true)['game']);
    }

    // ------------------------------------------------------------------------------------------------------------------- finishing

    public function test_a_finished_game_is_reported_like_any_other(): void
    {
        $kid = $this->child(1);

        $this->postJson(route('learner.games.finish', 'sound-hunt'), ['level_reached' => 3, 'top_level_cleared' => true, 'had_perfect_round' => true, 'rounds' => 2])
            ->assertOk()->assertJsonStructure(['newBadges']);

        $row = GamePlay::where('learner_id', $kid->id)->first();
        $this->assertSame('sound-hunt', $row->game);
        $this->assertSame(3, $row->level_reached);
        $this->assertContains('sound-hunt', GamePlay::GAMES);

        // Free play: it never changes points, streak or level.
        $kid->refresh();
        $this->assertSame(40, $kid->points);
        $this->assertSame(3, $kid->streak);
        $this->assertSame('Developing', $kid->mastery_level);

        $this->postJson(route('learner.games.finish', 'no-such-game'), ['level_reached' => 1, 'top_level_cleared' => false, 'had_perfect_round' => false, 'rounds' => 1])->assertNotFound();
    }

    // ------------------------------------------------------------------------------------------------------------------- Tara's voice clips

    public function test_the_clip_list_command_gives_the_script_what_it_needs(): void
    {
        $dir = 'audio/sound-hunt-test-'.uniqid();
        config(['sound_hunt.clips.directory' => $dir]);
        File::ensureDirectoryExists(public_path($dir));
        try {
            File::put(public_path("$dir/w-cat.mp3"), 'x');

            Artisan::call('sound-hunt:clips', ['--json' => true]);
            $all = json_decode(Artisan::output(), true);
            $this->assertSame(SoundHunt::clipKeys(), $all['clips']);
            $this->assertSame(count(SoundHunt::clipKeys()), $all['total']);
            $this->assertSame(['w-cat'], $all['existing']);
            $this->assertSame(config('sound_hunt.clips.voice'), $all['voice']);

            Artisan::call('sound-hunt:clips', ['--json' => true, '--missing' => true]);
            $missing = json_decode(Artisan::output(), true);
            $this->assertArrayNotHasKey('w-cat', $missing['clips']);
            $this->assertCount(count(SoundHunt::clipKeys()) - 1, $missing['clips']);
        } finally {
            File::deleteDirectory(public_path($dir));
        }
    }

    public function test_only_clips_that_exist_are_offered(): void
    {
        $dir = 'audio/sound-hunt-test-'.uniqid();
        config(['sound_hunt.clips.directory' => $dir]);
        $this->assertSame([], SoundHunt::clips(), 'a folder that does not exist offers nothing');

        File::ensureDirectoryExists(public_path($dir));
        try {
            $this->assertSame([], SoundHunt::clips(), 'an empty folder offers nothing');

            File::put(public_path("$dir/w-cat.mp3"), 'x');
            File::put(public_path("$dir/ask-rhyme.mp3"), 'x');
            File::put(public_path("$dir/not-a-clip.mp3"), 'x');

            $clips = SoundHunt::clips();
            $this->assertEqualsCanonicalizing(['ask-rhyme', 'w-cat'], array_keys($clips));
            $this->assertStringEndsWith("$dir/w-cat.mp3", $clips['w-cat']);

            $this->child(3);
            $html = $this->get(route('learner.games.sound-hunt'))->assertOk()->getContent();
            $this->assertStringContainsString('w-cat', $html);
            $this->assertStringNotContainsString('not-a-clip', $html);
        } finally {
            File::deleteDirectory(public_path($dir));
        }
    }
}
