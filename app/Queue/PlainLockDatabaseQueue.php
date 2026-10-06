<?php

namespace App\Queue;

use Illuminate\Queue\DatabaseQueue;

/**
 * The database queue, taking the next job with a plain "FOR UPDATE" lock.
 *
 * Laravel's own database queue asks for "FOR UPDATE SKIP LOCKED" on any server that reports
 * MySQL 8.0.1 or newer. TiDB reports a MySQL 8 version, and TiDB's documentation lists SKIP LOCKED
 * as unsupported. TESTED 2026-10-06 against TiDB Cloud Starter v8.5.3: that version ACCEPTS the
 * syntax (it does not honour it, but it does not fail either), so the worker ran with or without
 * this class. It is kept as a safeguard for a TiDB version that rejects the syntax, and because a
 * plain "FOR UPDATE" is the correct lock for this app anyway: there is one worker.
 *
 * This class is only used when QUEUE_POP_LOCK=plain is set (see App\Queue\PlainLockDatabaseConnector);
 * with it unset the queue is Laravel's own, unchanged.
 */
class PlainLockDatabaseQueue extends DatabaseQueue
{
    /** @return string|bool */
    protected function getLockForPopping()
    {
        return true; // the query builder turns `true` into "for update"
    }
}
