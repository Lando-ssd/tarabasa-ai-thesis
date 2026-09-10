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
     * Every defined badge (config/badges.php), merged with this Learner's
     * real earned/unearned state — the one shared source both the inline
     * "My Badges" section on the Dashboard (LearnerAuthController) and
     * the mobile API's dashboard endpoint build from, so the two can't
     * drift into two different answers for "which badges does this
     * Learner have." Same shared-method pattern already established by
     * ReadingSession::sourceSummaryForLearner().
     */
    public static function summaryFor(Learner $learner): array
    {
        $earned = self::where('learner_id', $learner->id)->get()->keyBy('badge_code');

        return collect(config('badges'))->map(function (array $def, string $code) use ($earned) {
            $row = $earned->get($code);

            return array_merge($def, [
                'code' => $code,
                'earned' => $row !== null,
                'earnedAt' => $row?->earned_at,
            ]);
        })->values()->all();
    }
}
