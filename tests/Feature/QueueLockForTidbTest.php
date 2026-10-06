<?php

namespace Tests\Feature;

use App\Queue\PlainLockDatabaseQueue;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\DatabaseQueue;
use Mockery;
use PDO;
use ReflectionMethod;
use Tests\TestCase;

/**
 * TiDB's documentation lists "FOR UPDATE SKIP LOCKED" as unsupported, yet TiDB reports a MySQL 8 version, so
 * Laravel's database queue asks for it (TiDB Cloud Starter v8.5.3 was tested on 2026-10-06 and accepts it, so
 * this is a safeguard, not a fix for a known failure). QUEUE_POP_LOCK=plain switches to a plain lock; with it
 * unset the queue must be exactly Laravel's own so other hosts (Railway) are not affected.
 */
class QueueLockForTidbTest extends TestCase
{
    use RefreshDatabase;

    private function lockOf(DatabaseQueue $queue): mixed
    {
        $method = new ReflectionMethod($queue, 'getLockForPopping');
        $method->setAccessible(true);

        return $method->invoke($queue);
    }

    /** A connection that looks like TiDB: MySQL driver, version string "8.0.11-TiDB-...". */
    private function tidbLikeConnection(): Connection
    {
        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('getAttribute')->with(PDO::ATTR_DRIVER_NAME)->andReturn('mysql');
        $pdo->shouldReceive('getAttribute')->with(PDO::ATTR_SERVER_VERSION)->andReturn('8.0.11-TiDB-v8.5.0');

        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getPdo')->andReturn($pdo);
        $connection->shouldReceive('getConfig')->with('version')->andReturn(null);

        return $connection;
    }

    public function test_laravel_alone_asks_a_tidb_like_server_for_skip_locked(): void
    {
        $queue = new DatabaseQueue($this->tidbLikeConnection(), 'jobs', 'default');

        $this->assertSame('FOR UPDATE SKIP LOCKED', $this->lockOf($queue));
    }

    public function test_the_plain_lock_queue_asks_for_a_plain_for_update(): void
    {
        $queue = new PlainLockDatabaseQueue($this->tidbLikeConnection(), 'jobs', 'default');

        $this->assertTrue($this->lockOf($queue));
    }

    public function test_with_the_switch_unset_the_queue_is_laravels_own_class(): void
    {
        config(['queue.connections.database.pop_lock' => null]);

        $queue = app('queue')->connection('database');

        $this->assertSame(DatabaseQueue::class, get_class($queue));
    }

    public function test_with_the_switch_on_the_queue_uses_the_plain_lock_and_still_runs_a_job(): void
    {
        config([
            'queue.default' => 'database',
            'queue.connections.database.pop_lock' => 'plain',
        ]);

        $queue = app('queue')->connection('database');

        $this->assertInstanceOf(PlainLockDatabaseQueue::class, $queue);
        $this->assertTrue($this->lockOf($queue));

        // A real push and pop through the plain lock, against the test database.
        $queue->pushRaw(json_encode(['displayName' => 'x', 'job' => 'x', 'data' => []]));
        $job = $queue->pop();

        $this->assertNotNull($job, 'The worker could not take a job with the plain lock.');
    }

    public function test_an_unknown_switch_value_falls_back_to_laravels_own_queue(): void
    {
        config(['queue.connections.database.pop_lock' => 'something-else']);

        $this->assertSame(DatabaseQueue::class, get_class(app('queue')->connection('database')));
    }
}
