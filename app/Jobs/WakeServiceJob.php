<?php

namespace App\Jobs;

use App\Support\ServiceWake;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Wakes a sleeping teammate service in the background, while a child is reading or a teacher is filling in a form.
 *
 * It has to be a background job because waking a sleeping free service means staying connected to its health page
 * for 20 to 40 seconds, and a web request must never be held that long just for this (the old ping gave up after a
 * few seconds, which very likely cancelled the wake-up). If the worker is not running, nothing is lost: the real
 * request waits for the service itself (ServiceWake::await) before it is sent.
 */
class WakeServiceJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 1;

    /** @param  'reader'|'generator'|'recommender'  $service */
    public function __construct(public string $service) {}

    public function handle(): void
    {
        [$url, $seconds] = match ($this->service) {
            'generator' => [config('services.activity_ai.url'), (int) config('services.activity_ai.ready_wait', 100)],
            'recommender' => [config('services.adaptive_recommender.url'), (int) config('services.reading_ai.ready_wait', 100)],
            default => [config('services.reading_ai.url'), (int) config('services.reading_ai.ready_wait', 100)],
        };

        ServiceWake::await($url, $this->service, $seconds);
    }

    /**
     * Starts the wake-up without making the page wait for it. With a real queue the worker takes it; where
     * the queue is "sync" (local work, tests) it runs after the response is sent instead of inside the request.
     */
    public static function start(string $service): void
    {
        if (config('queue.default') === 'sync') {
            static::dispatchAfterResponse($service);

            return;
        }

        static::dispatch($service);
    }
}
