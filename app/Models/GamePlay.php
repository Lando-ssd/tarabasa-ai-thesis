<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A finished Practice Games session (Balloon Pop, Word Train, Sentence Stars or Syllable Steps: all four end with the child reading
 * something out loud). Only read by BadgeService (the Games badges); see the migration for why this exists at all.
 *
 * Word Builder and Letter Match, the first two games, were spelling and matching games rather than reading games and were
 * replaced. Their old rows stay (a finished game still counts toward "games played"), but nothing writes them any more.
 */
class GamePlay extends Model
{
    public const GAMES = ['balloon-pop', 'word-train', 'sentence-stars', 'syllable-steps'];

    /** The two retired games, kept only so rows already written under these names are still understood. */
    public const RETIRED = ['word-builder', 'letter-match'];

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
