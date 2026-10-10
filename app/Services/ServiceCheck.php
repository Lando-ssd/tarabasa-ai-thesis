<?php

namespace App\Services;

use App\Models\ServiceFailure;
use App\Support\ServiceReply;
use Illuminate\Http\Client\ConnectionException;
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
 * does any real work, which proves the request got through (a 5xx would mean it did not) without costing the
 * checker anything or sending any child's voice. No child data is used anywhere in this.
 *
 * Every probe ends in one of three states, so the Admin screen can tell a broken service from a sleeping one:
 *  - ok       it answered the way the app needs.
 *  - asleep   it gave NO ANSWER YET: the hosting turned this server's request away with "429 Too Many Requests", or
 *             nothing came back within the wait. Free hosting puts a service to sleep after about 15 quiet minutes,
 *             and a request from another hosted service does not wake it (see App\Support\ServiceWake). That says
 *             nothing is broken, so it is NOT counted as a problem.
 *  - problem  anything else: a server error, a refusal, a connection that failed at once, or something not set up.
 */
class ServiceCheck
{
    /**
     * @return array{summary: string, problems: int, notices: int, lines: array<int,string>, checks: array<int,array<string,mixed>>, ip: ?string}
     */
    public function run(): array
    {
        $lines = [];
        $checks = [];
        $problems = 0;
        $notices = 0;

        $ip = $this->publicAddress();
        $lines[] = 'This server reaches the internet as: '.($ip ?? 'unknown (could not ask)');

        // Not a teammate service, but a thing only this server can prove: it can draw a learner's QR code (the QR library
        // and the PHP extension it needs are installed here). Nothing is stored; the picture is thrown away.
        try {
            $qr = \App\Support\LearnerQr::svg('TB26-00005', 120);
            $qrOk = str_starts_with($qr, '<svg ') && strlen($qr) > 500;
            $lines[] = 'QR codes: '.($qrOk ? 'OK (this server can draw a learner QR code)' : 'PROBLEM (the QR code came out empty)');
            $checks[] = $this->entry('qr', 'QR codes', 'draw a learner QR code', $qrOk ? 'ok' : 'problem', null, 0, $qrOk ? '' : 'the QR code came out empty');
            $problems += $qrOk ? 0 : 1;
        } catch (\Throwable $e) {
            $lines[] = 'QR codes: PROBLEM (this server cannot draw a learner QR code: '.mb_substr($e->getMessage(), 0, 120).')';
            $checks[] = $this->entry('qr', 'QR codes', 'draw a learner QR code', 'problem', null, 0, mb_substr($e->getMessage(), 0, 120));
            $problems++;
        }

        $reader = config('services.reading_ai.url');
        $generator = config('services.activity_ai.url');
        $recommender = config('services.adaptive_recommender.url');

        $probes = [];
        $notSetUp = [];

        if ($reader) {
            $probes[] = $this->probe('reader', 'Reading checker', 'GET /health', fn () => Http::timeout(30)->get(rtrim($reader, '/').'/health'), [200]);
            // Two sizes: a very short one, and one as large as a real recording of a child reading a few words
            // (about 190 KB), in case the hosting treats a bigger upload differently.
            foreach ([[0.5, 'short silent recording'], [6.0, 'silent recording as long as a real reading']] as [$seconds, $label]) {
                $probes[] = $this->probe('reader', 'Reading checker', "POST /analyze ({$label})", fn () => Http::timeout(60)
                    ->attach('file', $this->silentWav($seconds), 'check.wav')
                    ->post(rtrim($reader, '/').'/analyze', [
                        'grade' => 1, 'subdomain' => 'Phonics and Word Study', 'competency_code' => 'RL1PWS-I-5',
                        'activity_type' => 'word_reading', 'difficulty' => 'easy', 'reference_text' => 'cat',
                    ]), [200, 422]);
            }
        } else {
            $lines[] = 'Reading checker: not configured (READING_AI_URL is empty)';
            $notSetUp['reader'] = 'READING_AI_URL is empty';
        }

        if ($generator) {
            $probes[] = $this->probe('generator', 'Activity generator', 'GET /health', fn () => Http::timeout(30)->get(rtrim($generator, '/').'/health'), [200]);
        } else {
            $lines[] = 'Activity generator: not configured (ACTIVITY_AI_URL is empty)';
            $notSetUp['generator'] = 'ACTIVITY_AI_URL is empty';
        }

        if ($recommender) {
            $probes[] = $this->probe('recommender', 'Adaptive recommender', 'GET /health', fn () => Http::timeout(30)->get(rtrim($recommender, '/').'/health'), [200]);
        } else {
            $lines[] = 'Adaptive recommender: not configured (ADAPTIVE_RECOMMENDER_URL is empty)';
            $notSetUp['recommender'] = 'ADAPTIVE_RECOMMENDER_URL is empty';
        }

        // Email: can the app still sign in to Gmail to send? Asked by exchanging the stored refresh token for a short
        // access token, which sends nothing and emails nobody. The token that comes back is never written down.
        $gmail = config('services.gmail_send');

        if (config('mail.default') === 'gmail-api' && ! empty($gmail['refresh_token'])) {
            $probes[] = $this->probe('mail', 'Email (Gmail)', 'sign in with the stored token', fn () => Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
                'client_id' => $gmail['client_id'], 'client_secret' => $gmail['client_secret'], 'refresh_token' => $gmail['refresh_token'], 'grant_type' => 'refresh_token',
            ]), [200], secret: true, team: false);
        } elseif (config('mail.default') === 'gmail-api') {
            $lines[] = 'Email (Gmail): not configured (GMAIL_SEND_REFRESH_TOKEN is empty), so no email can be sent';
            $checks[] = $this->entry('mail', 'Email (Gmail)', 'sign in with the stored token', 'problem', null, 0, 'GMAIL_SEND_REFRESH_TOKEN is empty, so no email can be sent');
            $problems++;
        }

        foreach ($notSetUp as $key => $why) {
            $checks[] = $this->entry($key, ['reader' => 'Reading checker', 'generator' => 'Activity generator', 'recommender' => 'Adaptive recommender'][$key], 'not set up', 'off', null, 0, $why);
        }

        foreach ($probes as $probe) {
            $started = microtime(true);

            try {
                /** @var Response $response */
                $response = ($probe['call'])();
                $ms = (int) round((microtime(true) - $started) * 1000);
                $status = $response->status();
                $ok = in_array($status, $probe['good'], true);
                // An answer that carries a secret (the Gmail access token) is never repeated.
                $said = $probe['secret'] ? ($ok ? '' : (string) ($response->json('error') ?? '').' '.(string) ($response->json('error_description') ?? '')) : (ServiceReply::detail($response) ?? ServiceReply::snippet($response));
                $said = trim($said);
                $state = $ok ? 'ok' : (($probe['team'] && $status === 429) ? 'asleep' : 'problem');
                $lines[] = sprintf('%s, %s: %s (%s) in %.1f s%s', $probe['who'], $probe['what'], $this->label($state), $status, $ms / 1000, $said !== '' ? ', it said: '.mb_substr($said, 0, 120) : '');
                $checks[] = $this->entry($probe['key'], $probe['who'], $probe['what'], $state, $status, $ms, mb_substr($said, 0, 120));
            } catch (\Throwable $e) {
                $ms = (int) round((microtime(true) - $started) * 1000);
                // Nothing came back within the wait. For a service that sleeps on free hosting that is the usual cold start.
                $timedOut = $e instanceof ConnectionException && (str_contains(strtolower($e->getMessage()), 'timed out') || str_contains($e->getMessage(), 'cURL error 28'));
                $state = ($probe['team'] && $timedOut) ? 'asleep' : 'problem';
                $lines[] = sprintf('%s, %s: %s (no answer) in %.1f s, %s', $probe['who'], $probe['what'], $this->label($state), $ms / 1000, mb_substr($e->getMessage(), 0, 140));
                $checks[] = $this->entry($probe['key'], $probe['who'], $probe['what'], $state, null, $ms, mb_substr($e->getMessage(), 0, 140));
            }

            $problems += $state === 'problem' ? 1 : 0;
            $notices += $state === 'asleep' ? 1 : 0;
        }

        $total = count($probes);

        if ($problems === 0 && $notices === 0) {
            $summary = 'Service check from the app server: every service answered normally.';
        } elseif ($problems === 0) {
            $summary = "Service check from the app server: nothing is broken. {$notices} of {$total} checks got no answer yet, because free hosting puts a service to sleep when nobody uses it.";
        } else {
            $summary = "Service check from the app server: {$problems} of {$total} checks had a problem.";
        }

        return ['summary' => $summary, 'problems' => $problems, 'notices' => $notices, 'lines' => $lines, 'checks' => $checks, 'ip' => $ip];
    }

    /** Runs the check and writes the result to the diary the Admin screens show. */
    public function runAndRecord(): array
    {
        $result = $this->run();

        ServiceFailure::record('check', null, $result['summary'], [], implode("\n", $result['lines']), [
            'checked_at' => now()->toIso8601String(),
            'ip' => $result['ip'],
            'problems' => $result['problems'],
            'notices' => $result['notices'],
            'checks' => $result['checks'],
        ]);

        // A check runs every time the app starts, so older ones add nothing: the newest few are enough.
        try {
            $keep = ServiceFailure::where('service', 'check')->orderByDesc('id')->limit(30)->pluck('id');
            ServiceFailure::where('service', 'check')->whereNotIn('id', $keep)->delete();
        } catch (\Throwable) {
            // The diary is a convenience; it must never stop a check.
        }

        return $result;
    }

    private function probe(string $key, string $who, string $what, \Closure $call, array $good, bool $secret = false, bool $team = true): array
    {
        return ['key' => $key, 'who' => $who, 'what' => $what, 'call' => $call, 'good' => $good, 'secret' => $secret, 'team' => $team];
    }

    private function entry(string $key, string $who, string $what, string $state, ?int $http, int $ms, string $said): array
    {
        return ['key' => $key, 'who' => $who, 'what' => $what, 'state' => $state, 'http' => $http, 'ms' => $ms, 'said' => $said];
    }

    private function label(string $state): string
    {
        return ['ok' => 'OK', 'asleep' => 'NO ANSWER YET', 'problem' => 'PROBLEM'][$state];
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
