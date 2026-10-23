<?php

/**
 * Scoring recordings in the background (App\Services\BackgroundScoring).
 *
 * Scoring waits on the speech service for seconds, sometimes close to a minute when it has been asleep. Held inside a
 * web request that uses up one of the server's few web workers (four on the free host), so a class pressing "done"
 * together left none for the host's own health check and every visitor got a 502. Now the child's page sends the
 * recording and a background worker scores it while the page asks "is it done?" every couple of seconds.
 */
return [
    // A job still queued or running after this many seconds is given up on (its worker, or the whole server, was lost).
    // The child's page then offers "Send Again". Longer than the scoring service is ever waited for (job_hold below).
    'stale_after' => (int) env('SCORING_STALE_AFTER', 240),

    // If no background worker has picked a recording up within this many seconds, the page that is waiting for it
    // scores it itself (the same safety net "Generate activities" has), so the app still works with no worker running.
    'inline_after' => (int) env('SCORING_INLINE_AFTER', 12),

    // How long a background worker may wait on the speech service for ONE recording (waking it, sending, asking again).
    // A web request is held to services.reading_ai.max_hold (45); a worker is not a web worker, so it can wait longer.
    'job_hold' => (int) env('SCORING_JOB_HOLD', 90),

    // How many web workers scoring may use at once on the routes that still score inside the request (the phone app
    // and the practice games). The rest of the workers always stay free for pages and for the host's health check.
    'web_slots' => (int) env('SCORING_WEB_SLOTS', 2),

    // Finished jobs (their result pages) are kept this many hours, then removed.
    'keep_hours' => (int) env('SCORING_KEEP_HOURS', 24),
];
