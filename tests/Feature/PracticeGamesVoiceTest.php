<?php

namespace Tests\Feature;

use App\Models\GamePlay;
use App\Models\Learner;
use App\Models\PersonalWordBank;
use App\Models\ReadingSession;
use App\Services\DiagnosticBank;
use App\Support\LearnerCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Practice Games with a voice: a Listen button, a say-it step, and the new speaking game Balloon Pop.
 * Games stay free play, so nothing a child says may ever be saved or move points, streak or level.
 */
class PracticeGamesVoiceTest extends TestCase
{
    use RefreshDatabase;

    private function child(array $o = []): Learner
    {
        $kid = Learner::create($o + [
            'learner_code' => LearnerCode::generate(), 'first_name' => 'Maria', 'last_name' => 'Cruz', 'grade_level' => 'Grade 1',
            'pin' => '1234', 'avatar_id' => 'A', 'mastery_level' => 'Developing', 'points' => 40, 'streak' => 3,
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

    private function clip(): UploadedFile
    {
        return UploadedFile::fake()->create('word.webm', 20, 'audio/webm');
    }

    private function fakeAnalysis(string $word, string $status, ?string $spoken = null): array
    {
        return [
            'accuracy' => [
                'accuracy_score' => $status === 'correct' ? 100 : 0, 'spoken_word_count' => 1, 'substitutions' => 0, 'deletions' => 0, 'insertions' => 0,
                'word_feedback' => [['reference' => $word, 'spoken' => $spoken ?? $word, 'status' => $status]],
            ],
            'speed' => ['wcpm' => 40], 'prosody' => ['prosody_score' => 50],
            'word_timestamps' => [['word' => $spoken ?? $word, 'start' => 0, 'end' => 0.5, 'confidence' => 0.9]],
        ];
    }

    // ------------------------------------------------------------- the hub and the pages

    public function test_the_games_hub_offers_three_games_and_says_games_are_never_graded(): void
    {
        $this->child();

        $this->get(route('learner.games.index'))->assertOk()
            ->assertSee('Balloon Pop')->assertSee('A balloon shows a word. Say the word out loud to pop it!')
            ->assertSee('Hear a letter, then find it and its small twin.')
            ->assertSee('you can hear the word first, and say it when you finish', false)
            ->assertSee('what you say in a game is not graded')
            ->assertSee('Where the words come from')
            ->assertSee(route('learner.games.balloon-pop'), false);
    }

    public function test_every_game_page_has_its_voice(): void
    {
        $this->child();

        $this->get(route('learner.games.word-builder'))->assertOk()
            ->assertSee('id="listenBtn"', false)->assertSee('tarabasaListenForWord', false)->assertSee('Now say it out loud.');
        $this->get(route('learner.games.letter-match'))->assertOk()
            ->assertSee('tarabasaSayLetter', false)->assertSee('Tap a card to turn it over and hear the letter.');
        $this->get(route('learner.games.balloon-pop'))->assertOk()
            ->assertSee('Tap the microphone and say the word.')->assertSee('There is no wrong answer here.')->assertSee('tarabasaListenForWord', false);
    }

    public function test_balloon_pop_uses_the_childs_own_tricky_words_first(): void
    {
        $kid = $this->child();
        foreach (['cow', 'hen'] as $w) {
            PersonalWordBank::create(['learner_id' => $kid->id, 'word' => $w, 'mastery_status' => 'Struggling']);
        }

        $page = $this->get(route('learner.games.balloon-pop'))->assertOk();
        // The three-letter words the child has missed are sent to the game ahead of the fixed list.
        $page->assertSee('"struggling":["cow","hen"]', false);
    }

    public function test_the_games_are_behind_the_first_check_and_a_learner_login(): void
    {
        $this->post(route('learner.games.check-word'), [])->assertRedirect();
        $this->get(route('learner.games.balloon-pop'))->assertRedirect();

        $fresh = Learner::create([
            'learner_code' => LearnerCode::generate(), 'first_name' => 'New', 'last_name' => 'Kid', 'grade_level' => 'Grade 1',
            'pin' => '1234', 'avatar_id' => 'A',
        ]);
        $this->actingAs($fresh, 'learner')->get(route('learner.games.balloon-pop'))->assertRedirect(route('learner.diagnostic.show'));
    }

    // ------------------------------------------------------------- the one-word check

    public function test_a_word_said_right_is_heard_and_nothing_is_saved(): void
    {
        $kid = $this->child();
        Http::fake(['reading.test/analyze' => Http::response($this->fakeAnalysis('dog', 'correct'))]);

        $this->postJson(route('learner.games.check-word'), ['word' => 'dog', 'audio' => $this->clip()])
            ->assertOk()->assertExactJson(['status' => 'heard']);

        // The scorer was told it is one word at the child's own grade, with that grade's curriculum code.
        Http::assertSent(function ($request) {
            $body = $request->body();

            return str_contains($body, 'reference_text') && str_contains($body, 'dog')
                && str_contains($body, 'RL1PWS-I-5') && str_contains($body, 'word_reading')
                && str_contains($body, 'Phonics and Word Study');
        });

        // Practice only: no reading, no word bank, no points, no streak, no level.
        $kid->refresh();
        $this->assertSame([40, 3, 'Developing'], [$kid->points, $kid->streak, $kid->mastery_level]);
        $this->assertSame(1, ReadingSession::count(), 'only the finished first check, nothing from the game');
        $this->assertSame(0, PersonalWordBank::count());
    }

    public function test_a_word_said_differently_only_ever_gets_again_never_wrong(): void
    {
        $this->child();
        Http::fake(['reading.test/analyze' => Http::response($this->fakeAnalysis('dog', 'substitution', 'dig'))]);

        $res = $this->postJson(route('learner.games.check-word'), ['word' => 'dog', 'audio' => $this->clip()])->assertOk();

        $res->assertExactJson(['status' => 'again']);
        $this->assertStringNotContainsStringIgnoringCase('wrong', $res->getContent());
        $this->assertStringNotContainsString('dig', $res->getContent(), 'what the recognizer heard is not handed back to the browser');
    }

    public function test_silence_is_again_and_an_unreachable_service_means_the_game_just_carries_on(): void
    {
        $this->child();

        Http::fake(['reading.test/analyze' => Http::response(['detail' => 'Audio is silent or nearly silent.'], 422)]);
        $this->postJson(route('learner.games.check-word'), ['word' => 'dog', 'audio' => $this->clip()])->assertExactJson(['status' => 'again']);

        Http::fake(['reading.test/analyze' => fn () => throw new ConnectionException('down')]);
        $this->postJson(route('learner.games.check-word'), ['word' => 'dog', 'audio' => $this->clip()])->assertExactJson(['status' => 'unavailable']);

        config(['services.reading_ai.url' => null]);
        $this->postJson(route('learner.games.check-word'), ['word' => 'dog', 'audio' => $this->clip()])->assertExactJson(['status' => 'unavailable']);
    }

    public function test_the_word_and_clip_are_checked_before_anything_is_sent(): void
    {
        $this->child();
        Http::fake();

        foreach (['DOG', 'd', 'dog dog', 'dog1', 'a-b', str_repeat('a', 30)] as $bad) {
            $this->postJson(route('learner.games.check-word'), ['word' => $bad, 'audio' => $this->clip()])->assertStatus(422);
        }
        $this->postJson(route('learner.games.check-word'), ['word' => 'dog'])->assertStatus(422);
        $this->postJson(route('learner.games.check-word'), ['word' => 'dog', 'audio' => UploadedFile::fake()->create('big.webm', 4096)])->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_a_grade_three_child_is_checked_against_the_grade_three_code(): void
    {
        $this->child(['grade_level' => 'Grade 3']);
        Http::fake(['reading.test/analyze' => Http::response($this->fakeAnalysis('castle', 'correct'))]);

        $this->postJson(route('learner.games.check-word'), ['word' => 'castle', 'audio' => $this->clip()])->assertExactJson(['status' => 'heard']);
        Http::assertSent(fn ($r) => str_contains($r->body(), 'EN3PWS-I-2'));
    }

    // ------------------------------------------------------------- finishing, and waking the service

    public function test_a_balloon_pop_session_is_recorded_for_the_games_badges_like_the_other_games(): void
    {
        $kid = $this->child();

        $this->postJson(route('learner.games.finish', 'balloon-pop'), [
            'level_reached' => 2, 'top_level_cleared' => false, 'had_perfect_round' => true, 'rounds' => 3,
        ])->assertOk()->assertJsonStructure(['newBadges']);

        $this->assertSame(1, GamePlay::where('learner_id', $kid->id)->where('game', 'balloon-pop')->count());
        $this->postJson(route('learner.games.finish', 'no-such-game'), [
            'level_reached' => 1, 'top_level_cleared' => false, 'had_perfect_round' => false, 'rounds' => 1,
        ])->assertNotFound();
    }

    public function test_opening_a_voice_screen_starts_waking_the_scoring_service_in_the_background_once_in_a_while(): void
    {
        $this->child();
        Cache::flush();
        config(['queue.default' => 'database', 'services.adaptive_recommender.url' => 'https://recommender.test']);
        \Illuminate\Support\Facades\Queue::fake();
        Http::fake();

        $this->get(route('learner.warm'))->assertNoContent();
        $this->get(route('learner.warm'))->assertNoContent();
        $this->get(route('learner.warm'))->assertNoContent();

        // the page itself never talks to the service (that would hold the child's screen); a background job does
        Http::assertNothingSent();
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\WakeServiceJob::class, 2); // the checker once, the recommender once
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\WakeServiceJob::class, fn ($j) => $j->service === 'reader');
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\WakeServiceJob::class, fn ($j) => $j->service === 'recommender');
    }

    public function test_a_service_known_to_be_awake_is_not_woken_again(): void
    {
        $this->child();
        Cache::flush();
        config(['queue.default' => 'database']);
        \Illuminate\Support\Facades\Queue::fake();
        \App\Support\ServiceWake::markAwake('reader');

        $this->get(route('learner.warm'))->assertNoContent();

        \Illuminate\Support\Facades\Queue::assertNotPushed(\App\Jobs\WakeServiceJob::class, fn ($j) => $j->service === 'reader');
    }

    public function test_the_wake_up_job_stays_connected_to_the_health_page_and_never_fails_when_the_service_is_down(): void
    {
        Cache::flush();
        config(['services.reading_ai.url' => 'https://reading.test', 'services.reading_ai.ready_wait' => 1, 'services.retry_pause' => 0]); // never the real service

        Http::fake(['reading.test/health' => Http::response(['status' => 'ok'])]);
        (new \App\Jobs\WakeServiceJob('reader'))->handle();
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/health'));
        $this->assertTrue(\App\Support\ServiceWake::isKnownAwake('reader'), 'after it answered, nobody asks again for a while');

        Cache::flush();
        config(['services.reading_ai.url' => 'https://down.test']); // a different host, because the first stub for a host wins
        Http::fake(['down.test/health' => fn () => throw new ConnectionException('asleep')]);
        (new \App\Jobs\WakeServiceJob('reader'))->handle();
        $this->assertFalse(\App\Support\ServiceWake::isKnownAwake('reader'));
    }

    public function test_the_childs_pages_wake_the_sleeping_services_from_the_browser_and_never_print_a_key(): void
    {
        $this->child();
        config([
            'services.adaptive_recommender.url' => 'https://recommender.test', 'services.adaptive_recommender.key' => 'RECOMMENDER-SECRET-KEY',
            'services.activity_ai.url' => 'https://generator.test', 'services.activity_ai.key' => 'GENERATOR-SECRET-KEY',
        ]);

        // A request from this server cannot wake a sleeping Render service, so the page asks the health pages itself.
        foreach ([route('learner.dashboard'), route('learner.games.index'), route('learner.games.balloon-pop')] as $url) {
            $page = $this->get($url)->assertOk();
            $page->assertSee('tb-wake:', false)->assertSee('https:\/\/reading.test\/health', false);
            $this->assertStringNotContainsString('SECRET-KEY', $page->getContent(), 'a key is never put on a page');
        }

        $this->get(route('learner.dashboard'))->assertSee('https:\/\/recommender.test\/health', false)->assertDontSee('generator.test', false);
    }
}
