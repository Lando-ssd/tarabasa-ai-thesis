<?php

namespace App\Support;

use App\Models\ServiceFailure;
use Carbon\CarbonInterface;

/**
 * What the Admin's Overview and System health screens say about the four things the platform depends on: the reading
 * checker, the activity generator, the adaptive recommender and email sending. It reads the newest service check (see
 * App\Services\ServiceCheck) and the diary of failed calls, and turns them into one plain card per service.
 *
 * A card is in one of these states:
 *  ok       it answered the way the app needs.
 *  asleep   no answer yet: free hosting puts a service to sleep after about 15 quiet minutes. Not a problem.
 *  problem  something is wrong and a person should look.
 *  off      not set up (its address or key is empty).
 *  unknown  no check has been run yet.
 *  na       email is not sent through Gmail on this host, so there is nothing to check.
 */
class AdminHealth
{
    /** key => [title, what it does, what happens when it is down] */
    public const SERVICES = [
        'reader' => ['Reading checker', 'Scores what children read aloud.', 'If it is down, readings cannot be scored.'],
        'generator' => ['Activity generator', 'Writes the activities teachers ask for.', 'If it is down, teachers cannot generate new activities. The ones they already have keep working.'],
        'recommender' => ['Adaptive recommender', 'Chooses what a child should read next.', 'If it is down, children still read. The list is just not tailored to them.'],
        'mail' => ['Email sending', 'Sends the verification emails to new teachers and parents.', 'If it is down, new accounts cannot verify their email.'],
    ];

    public static function latestCheck(): ?ServiceFailure
    {
        return ServiceFailure::where('service', 'check')->orderByDesc('id')->first();
    }

    /** @return array<string,mixed>|null what the newest check found, as data (null when none, or an older row without it) */
    public static function latestDetails(?ServiceFailure $check = null): ?array
    {
        $check ??= self::latestCheck();
        $details = $check?->details ? json_decode((string) $check->details, true) : null;

        return is_array($details) ? $details : null;
    }

    /**
     * One card per service, in a fixed order.
     *
     * @return array<int,array{key:string,title:string,does:string,ifdown:string,state:string,headline:string,lines:array<int,string>,ms:?int}>
     */
    public static function cards(): array
    {
        $check = self::latestCheck();
        $details = self::latestDetails($check);
        $checks = collect($details['checks'] ?? []);
        $mailDriver = (string) config('mail.default');

        // A real email that failed to send after the newest check (or when there is no check at all) is the freshest news.
        $mailFailure = ServiceFailure::where('service', 'mail')->where('created_at', '>=', now()->subDay())->orderByDesc('id')->first();
        $mailFailureIsNewer = $mailFailure && (! $check || $mailFailure->created_at->greaterThan($check->created_at));

        $cards = [];

        foreach (self::SERVICES as $key => [$title, $does, $ifdown]) {
            $mine = $checks->where('key', $key)->values();
            $state = 'unknown';
            $headline = 'Not checked yet. Press Check now.';
            $lines = [];
            $ms = null;

            if ($mine->isNotEmpty()) {
                $state = $mine->contains('state', 'problem') ? 'problem' : ($mine->contains('state', 'off') ? 'off' : ($mine->contains('state', 'asleep') ? 'asleep' : 'ok'));
                $ms = $mine->pluck('ms')->filter()->max() ?: null;
                $lines = $mine->map(fn ($c) => $c['what'].': '.self::describe($c))->all();
                $first = $mine->firstWhere('state', $state) ?? $mine->first();

                $headline = match ($state) {
                    'ok' => 'Answered normally'.($ms ? sprintf(', the slowest in %.1f seconds.', $ms / 1000) : '.'),
                    'asleep' => 'It had gone to sleep and gave no answer yet. Free hosting does this after about 15 quiet minutes. It wakes when a child or teacher uses the app, or when you press Check now.',
                    'off' => 'Not set up: '.($first['said'] ?: 'its address is empty').'.',
                    default => 'Something is wrong: '.self::sentence($first).'.',
                };
            } elseif ($key === 'mail' && $mailDriver !== 'gmail-api') {
                $state = 'na';
                $headline = 'Email is set to "'.$mailDriver.'" on this host, so it is not sent through Gmail.';
            }

            if ($key === 'mail' && $mailFailureIsNewer) {
                $state = 'problem';
                $headline = 'An email could not be sent: '.mb_substr((string) $mailFailure->what, 0, 140);
                $lines[] = 'Newest failed send: '.$mailFailure->created_at->timezone('Asia/Manila')->format('M j, g:i A');
            }

            $cards[] = ['key' => $key, 'title' => $title, 'does' => $does, 'ifdown' => $ifdown, 'state' => $state, 'headline' => $headline, 'lines' => $lines, 'ms' => $ms];
        }

        return $cards;
    }

    /** How many cards say something is really wrong (the number on the menu and on the Overview). */
    public static function problemCount(): int
    {
        return collect(self::cards())->where('state', 'problem')->count();
    }

    /**
     * Failures worth showing: every failed call of a service, and the service checks that found a real problem. A check
     * that only found sleeping services is routine and is left out, so the list is not full of the same non-news.
     *
     * @return \Illuminate\Support\Collection<int,ServiceFailure>
     */
    public static function problems(int $limit = 30)
    {
        return ServiceFailure::orderByDesc('id')->limit(200)->get()
            ->filter(function (ServiceFailure $f) {
                if ($f->service !== 'check') {
                    return true;
                }

                $d = $f->details ? json_decode((string) $f->details, true) : null;

                // A check row written before checks carried details cannot be told apart: most of them only counted
                // sleeping services as problems (the hosting's "Too Many Requests"), so they are left out. Their text is
                // still in the database. A new check always carries details, so nothing real is hidden from now on.
                return is_array($d) && ((int) ($d['problems'] ?? 0)) > 0;
            })
            ->take($limit)
            ->values();
    }

    /** The public addresses the browser may ask to wake each sleeping service (no keys; the same ones the other pages use). */
    public static function wakeUrls(): array
    {
        return collect([
            'reader' => config('services.reading_ai.url'),
            'recommender' => config('services.adaptive_recommender.url'),
            'generator' => config('services.activity_ai.url'),
        ])->filter()->map(fn ($u) => rtrim($u, '/').'/health')->all();
    }

    public static function whenText(?CarbonInterface $at): string
    {
        return $at ? $at->timezone('Asia/Manila')->format('M j, g:i A') : 'never';
    }

    /** One check as a short phrase for the "what each check found" list. */
    private static function describe(array $c): string
    {
        return match ($c['state']) {
            'ok' => 'working'.($c['http'] ? " ({$c['http']})" : ''),
            'asleep' => 'no answer yet'.($c['http'] ? " ({$c['http']})" : ''),
            'off' => 'not set up',
            default => self::sentence($c),
        };
    }

    private static function sentence(array $c): string
    {
        // No full stop at the end: the caller adds one, so it never doubles.
        $said = rtrim(trim((string) ($c['said'] ?? '')), '.');

        if ($c['http']) {
            return "the service answered {$c['http']}".($said !== '' ? ' and said "'.$said.'"' : '');
        }

        return $said !== '' ? $said : 'no answer';
    }
}
