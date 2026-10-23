<?php

namespace App\Jobs;

use App\Services\BackgroundScoring;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Scores one child's recording in the background (see App\Services\BackgroundScoring for the whole flow).
 *
 * It has its own queue, "scoring", with its own workers (docker/entrypoint.sh). The default queue also writes activities
 * with the AI, which takes minutes, and a recording must never wait behind that.
 */
class ScoreRecordingJob implements ShouldQueue
{
    use Queueable;

    /** Longer than the longest a worker waits on the speech service (config scoring.job_hold) plus saving. */
    public int $timeout = 240;

    /** Never run twice: a second run would count the same reading, and pay its points, twice. */
    public int $tries = 1;

    public function __construct(public string $jobId)
    {
        $this->onQueue('scoring');
    }

    public function handle(BackgroundScoring $scoring): void
    {
        $scoring->run($this->jobId);
    }
}
