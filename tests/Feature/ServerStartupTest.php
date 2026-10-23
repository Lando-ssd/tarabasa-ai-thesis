<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The live server is `php artisan serve`. Laravel only runs more than one worker when it is also given --no-reload; without the flag it
 * prints "Unable to respect the PHP_CLI_SERVER_WORKERS environment variable ... Only creating a single server." and runs ONE worker.
 * That is what ran in production until 2026-10-23: one slow request (a recording being scored) blocked every other request, including
 * the host's own health check, and the host answered everyone with a 502. The start script looked right and did nothing, so this
 * reads it.
 */
class ServerStartupTest extends TestCase
{
    private function script(): string
    {
        return (string) file_get_contents(base_path('docker/entrypoint.sh'));
    }

    public function test_the_serve_line_carries_no_reload_so_the_workers_setting_is_honoured(): void
    {
        $script = $this->script();

        $this->assertMatchesRegularExpression('/^exec php artisan serve\b.*--no-reload\b/m', $script, 'php artisan serve must be started with --no-reload, or PHP_CLI_SERVER_WORKERS is ignored and the site runs one worker');
    }

    public function test_more_than_one_worker_is_asked_for(): void
    {
        $this->assertSame(1, preg_match('/^export PHP_CLI_SERVER_WORKERS=(\d+)\s*$/m', $this->script(), $m), 'the script must set PHP_CLI_SERVER_WORKERS');
        $this->assertGreaterThanOrEqual(2, (int) $m[1]);
    }

    public function test_the_setting_is_exported_before_the_server_starts(): void
    {
        $script = $this->script();

        $this->assertLessThan(strpos($script, 'exec php artisan serve'), strpos($script, 'export PHP_CLI_SERVER_WORKERS='));
    }

    public function test_the_script_has_unix_line_endings(): void
    {
        $this->assertStringNotContainsString("\r", $this->script(), 'a Windows line ending in a shell script breaks it inside the Linux container');
    }

    public function test_laravel_still_works_the_way_the_script_assumes(): void
    {
        // If a Laravel upgrade changes this rule, the workers may silently stop again: fail loudly instead.
        $serve = (string) file_get_contents(base_path('vendor/laravel/framework/src/Illuminate/Foundation/Console/ServeCommand.php'));

        $this->assertStringContainsString('--no-reload', $serve);
        $this->assertStringContainsString('Only creating a single server', $serve, 'Laravel changed how artisan serve treats PHP_CLI_SERVER_WORKERS: re-check docker/entrypoint.sh and the live deploy log');
    }

    public function test_recordings_are_scored_by_workers_on_their_own_queue(): void
    {
        $script = $this->script();

        // The scoring workers: their own queue, so a recording never waits behind the minutes-long activity writing.
        $this->assertMatchesRegularExpression('/php artisan queue:work --queue=scoring\b/', $script);
        $this->assertMatchesRegularExpression('/SCORING_WORKERS:-\d+/', $script, 'how many scoring workers run must be a host setting with a default');

        // The existing worker stays on the default queue (activity writing), and no worker is left reading every queue.
        $this->assertMatchesRegularExpression('/php artisan queue:work --queue=default\b/', $script);
        $this->assertDoesNotMatchRegularExpression('/queue:work(?![^\n]*--queue=)/', $script, 'every queue worker must name its queue');
    }

    public function test_the_scoring_queue_name_in_the_job_matches_the_workers(): void
    {
        $job = new \App\Jobs\ScoreRecordingJob('x');

        $this->assertSame('scoring', $job->queue);
        $this->assertStringContainsString('--queue='.$job->queue, $this->script());
    }
}
