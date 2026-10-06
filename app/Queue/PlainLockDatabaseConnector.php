<?php

namespace App\Queue;

use Illuminate\Queue\Connectors\DatabaseConnector;

/**
 * Builds Laravel's normal database queue, unless the connection sets `pop_lock` to "plain"
 * (env QUEUE_POP_LOCK=plain), in which case it builds PlainLockDatabaseQueue. Used on hosts
 * whose database has no SKIP LOCKED (TiDB). Unset, the result is exactly Laravel's own queue.
 */
class PlainLockDatabaseConnector extends DatabaseConnector
{
    public function connect(array $config)
    {
        if (($config['pop_lock'] ?? null) !== 'plain') {
            return parent::connect($config);
        }

        return new PlainLockDatabaseQueue(
            $this->connections->connection($config['connection'] ?? null),
            $config['table'],
            $config['queue'],
            $config['retry_after'] ?? 60,
            $config['after_commit'] ?? null
        );
    }
}
