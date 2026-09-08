<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReadingSession extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'learner_id',
        'activity_id',
        'accuracy_percent',
        'wcpm',
        'speed_score',
        'prosody_score',
        'comprehension_score',
        'pronunciation_score',
        'fluency_score',
        'mispronunciation_count',
        'skipped_word_count',
        'substitution_count',
        'repetition_count',
        'insertion_count',
        'word_feedback',
        'adaptive_attempt_score',
        'level_before',
        'level_after',
        'flagged_needs_attention',
        'session_type',
        'initiated_by',
    ];

    protected function casts(): array
    {
        return [
            'flagged_needs_attention' => 'boolean',
            'timestamp' => 'datetime',
            'word_feedback' => 'array',
        ];
    }

    public function learner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function activity(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /**
     * Teacher-Assigned vs. Parent-Initiated counts + avg accuracy for one
     * Learner — shared by Teacher Analytics (By Learner), Parent Progress,
     * and the Parent Dashboard's per-child snapshot, since all three need
     * the exact same real numbers. Diagnostic sessions are always excluded
     * (confirmed with the user): a one-time placement test isn't ongoing
     * reading practice and its score isn't comparable to a practice
     * accuracy trend.
     */
    public static function sourceSummaryForLearner(int $learnerId): \Illuminate\Support\Collection
    {
        $sessions = self::where('learner_id', $learnerId)
            ->where('session_type', '!=', 'Diagnostic')
            ->get();

        return collect(['Teacher', 'Parent'])->mapWithKeys(function (string $source) use ($sessions) {
            $group = $sessions->where('initiated_by', $source);

            return [$source => [
                'count' => $group->count(),
                'avg_accuracy' => $group->isNotEmpty() ? round($group->avg('accuracy_percent')) : null,
            ]];
        });
    }
}
