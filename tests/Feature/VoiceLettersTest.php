<?php

namespace Tests\Feature;

use App\Models\Learner;
use App\Models\ReadingSession;
use App\Services\DiagnosticBank;
use App\Support\LearnerCode;
use App\Support\LetterVoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * How Tara says the letters (config/voice.php). The owner heard the letters A and I come out as the same sound: the spelling "ay" given to a
 * Philippine English voice is read like the Tagalog word "ay", which sounds like "eye". These pin the list that replaced it, and that the website,
 * the phone and the clip maker all read the same list. They cannot hear a voice: the page tools/voice-check.html and the clip sample are for ears.
 */
class VoiceLettersTest extends TestCase
{
    use RefreshDatabase;

    private function child(int $rung = 0): Learner
    {
        $kid = Learner::create([
            'learner_code' => LearnerCode::generate(), 'first_name' => 'Pia', 'last_name' => 'Cruz', 'grade_level' => 'Grade 1',
            'pin' => '1234', 'avatar_id' => 'A', 'mastery_level' => 'Beginning', 'reading_rung' => $rung, 'points' => 0, 'streak' => 0,
        ]);
        ReadingSession::create([
            'learner_id' => $kid->id, 'activity_id' => app(DiagnosticBank::class)->idsByRung()['letters'][0],
            'session_type' => 'Diagnostic', 'initiated_by' => 'Parent', 'accuracy_percent' => 80,
        ]);

        return $kid;
    }

    /** Runs $test with the letter clips (all, or all but one) made in a temporary folder, and cleans up. */
    private function withLetterClips(bool $all, callable $test): void
    {
        $dir = 'audio/sound-hunt-test-'.uniqid();
        config(['sound_hunt.clips.directory' => $dir]);
        File::ensureDirectoryExists(public_path($dir));
        try {
            $keys = array_keys(LetterVoice::clipKeys());
            foreach ($all ? $keys : array_slice($keys, 1) as $key) {
                File::put(public_path("$dir/$key.mp3"), 'x');
            }
            $test();
        } finally {
            File::deleteDirectory(public_path($dir));
        }
    }

    // ------------------------------------------------------------------------------------------------------------------- the list

    public function test_every_letter_has_its_own_spelling_so_a_and_i_cannot_sound_alike(): void
    {
        $names = LetterVoice::names();
        $this->assertSame(range('a', 'z'), array_keys($names));

        foreach ($names as $letter => $say) {
            $this->assertNotSame('', trim($say), "letter $letter has no spelling");
            $this->assertMatchesRegularExpression('/^[a-z ]+$/', $say, "letter $letter: a spelling is plain lowercase words");
        }
        $this->assertSame($names, array_unique($names), 'two letters were given the same spelling, so a voice would say them the same');

        // The mistake that was heard: "ay" is read as "eye" by a Philippine English voice, and "aye" is the English word for it.
        $this->assertNotContains($names['a'], ['ay', 'aye', 'eye', 'i']);
        $this->assertSame('eye', $names['i']);
        $this->assertNotSame($names['a'], $names['i']);
    }

    public function test_every_letter_has_a_distinct_phonetic_name_for_the_clip_maker(): void
    {
        $ipa = LetterVoice::ipa();
        $this->assertSame(range('a', 'z'), array_keys($ipa));
        $this->assertSame($ipa, array_unique($ipa));
        $this->assertSame('eɪ', $ipa['a']);
        $this->assertSame('aɪ', $ipa['i']);
    }

    // ------------------------------------------------------------------------------------------------------------------- the website

    public function test_the_website_says_letters_from_the_same_list(): void
    {
        $this->actingAs($this->child(0), 'learner');

        $html = $this->get(route('learner.games.word-train'))->assertOk()->getContent();
        $this->assertStringContainsString(json_encode(LetterVoice::names()), $html);
        $this->assertStringNotContainsString('"a":"ay"', $html);
        $this->assertStringContainsString('"a":"eigh"', $html);
    }

    public function test_the_website_uses_the_letter_clips_only_when_all_26_exist(): void
    {
        $this->actingAs($this->child(0), 'learner');

        $this->assertStringContainsString('var LETTER_CLIPS = {}', $this->get(route('learner.games.word-train'))->getContent(), 'no clips yet: the device voice reads the spelling');

        $this->withLetterClips(false, function () {
            $this->assertSame([], LetterVoice::clips(), '25 of 26 clips is not enough: the alphabet is never said in two voices');
            $this->assertStringContainsString('var LETTER_CLIPS = {}', $this->get(route('learner.games.word-train'))->getContent());
        });

        $this->withLetterClips(true, function () {
            $this->assertCount(26, LetterVoice::clips());
            $html = $this->get(route('learner.games.word-train'))->getContent();
            $this->assertStringContainsString('"l-a"', $html);
            $this->assertStringContainsString('"l-z"', $html);
        });
    }

    // ------------------------------------------------------------------------------------------------------------------- the phone

    public function test_the_phone_spells_the_letters_the_same_way(): void
    {
        $source = file_get_contents(base_path('mobile/src/gameKit.tsx'));
        $this->assertSame(1, preg_match('/export const LETTER_NAMES[^{]*\{(.*?)\};/s', $source, $block), 'LETTER_NAMES not found in gameKit.tsx');

        $this->assertGreaterThan(0, preg_match_all("/\b([a-z]): '([^']+)'/", $block[1], $m, PREG_SET_ORDER));
        $phone = [];
        foreach ($m as $pair) {
            $phone[$pair[1]] = $pair[2];
        }

        $this->assertSame(LetterVoice::names(), $phone, 'mobile/src/gameKit.tsx LETTER_NAMES must be the same list as config/voice.php');
    }

    public function test_the_phone_is_given_the_letter_clips_only_when_all_26_exist(): void
    {
        Sanctum::actingAs($this->child(0), ['*']);

        $this->assertSame([], $this->getJson('/api/learner/games/train')->assertOk()->json('clips'));

        $this->withLetterClips(false, function () {
            $this->assertSame([], $this->getJson('/api/learner/games/train')->assertOk()->json('clips'));
        });

        $this->withLetterClips(true, function () {
            $clips = $this->getJson('/api/learner/games/train')->assertOk()->json('clips');
            $this->assertSame(array_keys(LetterVoice::clipKeys()), array_keys($clips));
            $this->assertStringEndsWith('/l-a.mp3', $clips['l-a']);
        });
    }

    // ------------------------------------------------------------------------------------------------------------------- the clip maker

    public function test_the_clip_maker_says_a_letter_as_a_letter_and_a_word_as_a_word(): void
    {
        $script = file_get_contents(base_path('tools/make-voice-clips.py'));

        $this->assertStringContainsString("interpret-as='characters'", $script, 'a letter is spelled out, never read as the word "a"');
        $this->assertStringContainsString("alphabet='ipa'", $script, 'the sounds can be given instead');
        $this->assertStringContainsString("'l-a', 'l-i'", $script, 'the sample to listen to first includes A and I');
    }
}
