<?php

namespace App\Console\Commands;

use App\Services\ServiceCheck;
use Illuminate\Console\Command;

/**
 * Asks the three teammate services, from this server, whether they answer, and writes the result to the diary the
 * Admin dashboard shows ("Recent service problems"). docker/entrypoint.sh runs it in the background every time the app
 * starts, so after every deploy the team can see how the hosting treats this server's requests. See ServiceCheck.
 */
class CheckServices extends Command
{
    protected $signature = 'services:check';

    protected $description = 'Ask the activity generator, reading checker and adaptive recommender whether they answer this server';

    public function handle(ServiceCheck $check): int
    {
        // Sleeping free services can take a minute to answer their first request; this is not a web request.
        set_time_limit(400);

        $result = $check->runAndRecord();

        $this->line($result['summary']);

        foreach ($result['lines'] as $line) {
            $this->line('  '.$line);
        }

        return self::SUCCESS;
    }
}
