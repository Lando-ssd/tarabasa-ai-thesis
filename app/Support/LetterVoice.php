<?php

namespace App\Support;

/**
 * How Tara says the letters (config/voice.php): the spelling that makes a device voice say a letter's NAME, and the recorded letter clips.
 *
 * A letter is said from a recorded clip only when ALL 26 clips exist (clips()), so a child never hears some letters in one voice and the rest in
 * another. With no clips, or only some, the device's own read-aloud voice says every letter from its spelling (names()).
 */
class LetterVoice
{
    /** lowercase letter => how its name is spelled for a read-aloud voice. @return array<string, string> */
    public static function names(): array
    {
        return array_map(fn (array $l) => (string) $l['say'], config('voice.letters'));
    }

    /** lowercase letter => its name in the International Phonetic Alphabet (for the clip maker). @return array<string, string> */
    public static function ipa(): array
    {
        return array_map(fn (array $l) => (string) $l['ipa'], config('voice.letters'));
    }

    /** The clip key of a letter, as the games ask for it. */
    public static function key(string $letter): string
    {
        return 'l-'.strtolower($letter);
    }

    /** Every letter clip the app would like, key => the capital letter the clip maker is asked to say. @return array<string, string> */
    public static function clipKeys(): array
    {
        $keys = [];
        foreach (array_keys(config('voice.letters')) as $letter) {
            $keys[self::key($letter)] = strtoupper($letter);
        }

        return $keys;
    }

    /** The letter clips that exist on disk, key => address, even when only some of them do (the clip list uses this). @return array<string, string> */
    public static function existing(): array
    {
        $dir = public_path((string) config('sound_hunt.clips.directory'));
        $ext = (string) config('sound_hunt.clips.extension', 'mp3');
        if (! is_dir($dir)) {
            return [];
        }

        $found = [];
        foreach (array_keys(self::clipKeys()) as $key) {
            if (is_file("$dir/$key.$ext")) {
                $found[$key] = asset(config('sound_hunt.clips.directory')."/$key.$ext");
            }
        }

        return $found;
    }

    /** The clips the games may use: all 26 or none, so one child never hears two voices for the alphabet. @return array<string, string> */
    public static function clips(): array
    {
        $found = self::existing();

        return count($found) === count(self::clipKeys()) ? $found : [];
    }
}
