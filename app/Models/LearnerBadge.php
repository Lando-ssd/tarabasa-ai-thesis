<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LearnerBadge extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'learner_id',
        'badge_code',
        'earned_at',
    ];

    protected $casts = [
        'earned_at' => 'datetime',
    ];

    public function learner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    /**
     * Every defined badge merged with this learner's real earned state, the
     * one shared source for the Badges page and the mobile API. The rules
     * live in App\Services\BadgeService.
     */
    public static function summaryFor(Learner $learner): array
    {
        return app(\App\Services\BadgeService::class)->summaryFor($learner);
    }
}
