<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A finished Practice Games session. Only read by BadgeService (the Games badges);
 * see the migration for why this exists at all.
 */
class GamePlay extends Model
{
    public const GAMES = ['word-builder', 'letter-match'];

    public $timestamps = false;

    protected $fillable = [
        'learner_id',
        'game',
        'level_reached',
        'top_level_cleared',
        'had_perfect_round',
        'rounds',
        'played_at',
    ];

    protected $casts = [
        'top_level_cleared' => 'boolean',
        'had_perfect_round' => 'boolean',
        'played_at' => 'datetime',
    ];

    public function learner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }
}
