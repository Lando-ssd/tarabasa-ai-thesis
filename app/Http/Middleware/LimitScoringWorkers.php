<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps some web workers free for pages and for the host's health check, on the routes that still score a recording INSIDE
 * the request (the phone app, and the practice games' one-word checks). The website's reading screens no longer do: they hand
 * the recording to a background worker (App\Services\BackgroundScoring) and never hold a web worker.
 *
 * Each of those requests waits on the speech service for seconds. If every web worker is waiting like that, nothing else can be
 * answered, including the health check, and the host answers everybody with a 502. So at most config('scoring.web_slots')
 * of them run at once; one more is turned away at once with a plain "busy, try again in a moment" (the phone shows its
 * Send Again screen, the games carry on as if the microphone were not there), instead of taking the last worker.
 *
 * The count is a counter in the cache with a short life, so a request that dies without giving its slot back cannot block
 * scoring for more than a couple of minutes. It is a guard, not an exact meter.
 */
class LimitScoringWorkers
{
    private const KEY = 'scoring-web-slots';

    public function handle(Request $request, Closure $next): Response
    {
        $max = max(1, (int) config('scoring.web_slots', 2));

        Cache::add(self::KEY, 0, now()->addMinutes(2));
        $inUse = (int) Cache::increment(self::KEY);

        if ($inUse > $max) {
            $this->give();

            return response()->json([
                'status' => 'unavailable',
                'message' => 'Many children are reading at the same moment. Please wait a few seconds and try again.',
            ], 503)->header('Retry-After', '5');
        }

        try {
            return $next($request);
        } finally {
            $this->give();
        }
    }

    private function give(): void
    {
        $left = (int) Cache::decrement(self::KEY);

        // The counter's short life can end while a request is still running; never let it go below zero.
        if ($left < 0) {
            Cache::put(self::KEY, 0, now()->addMinutes(2));
        }
    }
}
