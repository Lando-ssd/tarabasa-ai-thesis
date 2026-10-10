<?php

namespace App\Support;

use App\Models\Teacher;

/**
 * The two numbers on the Admin menu: teachers waiting for approval and services with a real problem.
 * Worked out once per page by a view composer (AppServiceProvider), not by every controller.
 */
class AdminNav
{
    /** @return array{waiting:int, health:int} */
    public static function counts(): array
    {
        return [
            'waiting' => Teacher::where('status', 'Pending')->count(),
            'health' => AdminHealth::problemCount(),
        ];
    }
}
