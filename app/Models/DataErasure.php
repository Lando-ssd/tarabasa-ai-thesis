<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * One deletion done on request (a child's profile, or a whole account), kept after the data is gone. It never holds anything that
 * could identify the person: see the migration. Writing it must never be the reason a real deletion fails.
 */
class DataErasure extends Model
{
    public $timestamps = false;

    protected $fillable = ['kind', 'role', 'requested_by', 'children_erased'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public static function note(string $kind, string $role, string $requestedBy, int $childrenErased = 0): void
    {
        try {
            self::create(['kind' => $kind, 'role' => $role, 'requested_by' => $requestedBy, 'children_erased' => $childrenErased]);
        } catch (\Throwable $e) {
            Log::warning('Could not write the deletion record', ['error' => $e->getMessage()]);
        }
    }
}
