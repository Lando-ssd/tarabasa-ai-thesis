<?php

namespace App\Services;

use App\Models\ServiceFailure;
use App\Support\ServiceReply;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Asks each teammate service, from THIS server, whether it answers the way the app needs.
 *
 * Why this exists: on the live site the reading checker answered the app with "429 Too Many Requests" (plain
 * text, from the hosting in front of it) while it answered the developer's own computer normally. The only way to
 * tell what the hosting does to THIS server's requests is to ask from this server, so this runs on the server itself:
 * once every time the app starts (php artisan services:check) and whenever an Admin presses the button.
 *
 * The reading probe sends a very short silent recording on purpose. Reading-api rejects silence with a 422 before it
 * does any real work, which proves the request got through (a 429 or 5xx would mean it did not) without costing the
 * checker anything or sending any child's voice. No child data is used anywhere in this.
 */
class ServiceCheck
{
    /**
     * @return array{summary: string, problems: int, lines: array<int,string>}
     */
    public function run(): array
    {
        $lines = [];
        $problems = 0;

        $ip = $this->publicAddress();
        $lines[] = 'This server reaches the internet as: '.($ip ?? 'unknown (could not ask)');

        $reader = config('services.reading_ai.url');
        $generator = config('services.activity_ai.url');
        $recommender = config('services.adaptive_recommender.url');

        $probes = [];

        if ($reader) {
            $probes[] = ['Reading checker', 'GET /health', fn () => Http::timeout(30)->get(rtrim($reader, '/').'/health'), [200]];
            // Two sizes: a very short one, and one as large as a real recording of a child reading a few words
            // (about 190 KB), in case the hosting treats a bigger upload differently.
            foreach ([[0.5, 'short silent recording'], [6.0, 'silent recording as long as a real reading']] as [$seconds, $label]) {
                $probes[] = ['Reading checker', "POST /analyze ({$label})", fn () => Http::timeout(60)
                    ->attach('file', $this->silentWav($seconds), 'check.wav')
                    ->post(rtrim($reader, '/').'/analyze', [
                        'grade' => 1, 'subdomain' => 'Phonics and Word Study', 'competency_code' => 'RL1PWS-I-5',
                        'activity_type' => 'word_reading', 'difficulty' => 'easy', 'reference_text' => 'cat',
                    ]), [200, 422]];
            }
        } else {
            $lines[] = 'Reading checker: not configured (READING_AI_URL is empty)';
        }

        if ($generator) {
            $probes[] = ['Activity generator', 'GET /health', fn () => Http::timeout(30)->get(rtrim($generator, '/').'/health'), [200]];
        } else {
            $lines[] = 'Activity generator: not configured (ACTIVITY_AI_URL is empty)';
        }

        if ($recommender) {
            $probes[] = ['Adaptive recommender', 'GET /health', fn () => Http::timeout(30)->get(rtrim($recommender, '/').'/health'), [200]];
        } else {
            $lines[] = 'Adaptive recommender: not configured (ADAPTIVE_RECOMMENDER_URL is empty)';
        }

        foreach ($probes as [$who, $what, $call, $good]) {
            $started = microtime(true);

            try {
                /** @var Response $response */
                $response = $call();
                $ms = (int) round((microtime(true) - $started) * 1000);
                $ok = in_array($response->status(), $good, true);
                $said = ServiceReply::detail($response) ?? ServiceReply::snippet($response);
                $lines[] = sprintf('%s, %s: %s (%s) in %.1f s%s', $who, $what, $ok ? 'OK' : 'PROBLEM', $response->status(), $ms / 1000, $said !== '' ? ', it said: '.mb_substr($said, 0, 120) : '');
            } catch (\Throwable $e) {
                $ok = false;
                $lines[] = sprintf('%s, %s: PROBLEM (no answer) in %.1f s, %s', $who, $what, microtime(true) - $started, mb_substr($e->getMessage(), 0, 140));
            }

            $problems += $ok ? 0 : 1;
        }

        $summary = $problems === 0
            ? 'Service check from the app server: every service answered normally.'
            : "Service check from the app server: {$problems} of ".count($probes).' checks had a problem.';

        return ['summary' => $summary, 'problems' => $problems, 'lines' => $lines];
    }

    /** Runs the check and writes the result to the diary the Admin dashboard shows. */
    public function runAndRecord(): array
    {
        $result = $this->run();

        ServiceFailure::record('check', null, $result['summary'], [], implode("\n", $result['lines']));

        return $result;
    }

    /** Silence of the given length: 16 kHz, mono, 16 bit (what Reading-api works with). */
    private function silentWav(float $seconds = 0.5): string
    {
        $samples = (int) (16000 * $seconds);
        $data = str_repeat("\0\0", $samples);

        return 'RIFF'.pack('V', 36 + strlen($data)).'WAVE'
            .'fmt '.pack('VvvVVvv', 16, 1, 1, 16000, 32000, 2, 16)
            .'data'.pack('V', strlen($data)).$data;
    }

    /** The public address this server uses to reach the internet (shared by many apps on free hosting). */
    private function publicAddress(): ?string
    {
        try {
            $ip = trim(Http::timeout(6)->get('https://api.ipify.org')->body());

            return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
