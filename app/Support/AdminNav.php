<?php

namespace App\Support;

use App\Models\Teacher;
use App\Services\GradeChangeService;

/**
 * The two numbers on the Admin menu: things waiting for the Admin's approval (new teachers, and approved teachers asking for another grade) and services with a real problem.
 * Worked out once per page by a view composer (AppServiceProvider), not by every controller.
 */
class AdminNav
{
    /** @return array{waiting:int, health:int} */
    public static function counts(): array
    {
        return [
            'waiting' => Teacher::where('status', 'Pending')->count() + GradeChangeService::pendingCount(),
            'health' => AdminHealth::problemCount(),
        ];
    }
}
