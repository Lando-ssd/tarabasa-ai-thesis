<?php

namespace App\Console\Commands;

use App\Support\LetterVoice;
use App\Support\SoundHunt;
use Illuminate\Console\Command;

/**
 * Lists the voice clips the games would like to play: the key and the words of each one (every word Sound Hunt can say, Tara's short
 * phrases, and the 26 letter names Word Train says), and which of them already exist in public/audio/sound-hunt. tools/make-voice-clips.py reads this list (`--json`) to make the
 * missing ones with a text to speech voice, so the game and the script can never disagree about what has to be recorded.
 *
 *   php artisan sound-hunt:clips             a readable list, with the clips still to make
 *   php artisan sound-hunt:clips --json      the same as JSON, for the script
 *   php artisan sound-hunt:clips --missing   only the clips that do not exist yet
 */
class SoundHuntClips extends Command
{
    protected $signature = 'sound-hunt:clips {--json : Print the list as JSON} {--missing : Only the clips that do not exist yet}';

    protected $description = "List the voice clips Sound Hunt would play (Tara's phrases and every word), and which ones exist";

    public function handle(): int
    {
        // Sound Hunt's phrases and words, and the 26 letter names (Word Train's letters), all made into the same folder.
        $all = SoundHunt::clipKeys() + LetterVoice::clipKeys();
        $have = SoundHunt::clips() + LetterVoice::existing();
        $clips = $this->option('missing') ? array_diff_key($all, $have) : $all;

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'voice' => config('sound_hunt.clips.voice'),
                'directory' => config('sound_hunt.clips.directory'),
                'extension' => config('sound_hunt.clips.extension'),
                'total' => count($all),
                'existing' => array_keys($have),
                'clips' => $clips,
                // The keys that are letter names (said as letters, not read as words) and how each one sounds, for the script.
                'letters' => array_combine(
                    array_map(fn ($l) => LetterVoice::key($l), array_keys(LetterVoice::ipa())),
                    array_values(LetterVoice::ipa())
                ),
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->info(sprintf('%d clips wanted, %d already made (voice %s, folder public/%s).', count($all), count($have), config('sound_hunt.clips.voice'), config('sound_hunt.clips.directory')));
        foreach ($clips as $key => $text) {
            $this->line(sprintf('  %s  %-14s %s', isset($have[$key]) ? 'done' : 'todo', $key, $text));
        }

        return self::SUCCESS;
    }
}
