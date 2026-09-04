<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

/**
 * A Learner never has a password or email (Learner Actor Prompt, Rule 1)
 * but still needs its own login session, separate from the 'web' guard
 * Users authenticate on — see config/auth.php's 'learner' guard. Kept as
 * a plain Model implementing Authenticatable directly (not extending
 * Laravel's Foundation\Auth\User base) so it doesn't pick up unrelated
 * concerns like password-reset or email verification.
 */
class Learner extends Model implements AuthenticatableContract
{
    use Authenticatable;

    public $timestamps = false;

    protected $fillable = [
        'learner_code',
        'class_id',
        'first_name',
        'middle_name',
        'last_name',
        'grade_level',
        'pin',
        'avatar_id',
        'avatar_photo_path',
        'mastery_level',
        'learning_style',
        'points',
        'streak',
        'status',
    ];

    protected $hidden = [
        'pin',
    ];

    protected function casts(): array
    {
        return [
            // A Learner's PIN is a real credential (Learner Actor Prompt,
            // Step 1) even though it's only 4 digits — hashed exactly like
            // a User's password, never stored or compared in plain text.
            'pin' => 'hashed',
        ];
    }

    /**
     * The Authenticatable contract expects a "password" concept — a
     * Learner's real credential is its hashed PIN instead.
     */
    public function getAuthPassword(): string
    {
        return $this->pin;
    }

    public function schoolClass(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function parents(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(ParentAccount::class, 'parent_learners', 'learner_id', 'parent_id')
            ->withPivot(['relationship', 'is_creator', 'linked_at']);
    }

    public function promotionRecords(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PromotionRecord::class);
    }

    public function readingSessions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ReadingSession::class);
    }

    /**
     * LearnerHistory_Addition.txt Part 1 — computed from PromotionRecord
     * alone, no new table. A Pending record where THIS Learner is the
     * subject (released but not yet claimed) never needs handling here:
     * that means class_id is null, so this Learner isn't showing inside
     * any Teacher's Class Management roster to begin with.
     */
    public function promotionHistorySummary(): array
    {
        // Reads from the already-loaded relation (in-memory filter/sort)
        // rather than firing a fresh query, so eager-loading
        // `learners.promotionRecords.releasedFromClass` in Class
        // Management's index actually avoids N+1 across a whole roster.
        $claimed = $this->promotionRecords
            ->where('status', 'Claimed')
            ->sortBy('claimed_at')
            ->values();

        if ($claimed->isEmpty()) {
            return ['headline' => 'New to the system — no prior grade history.', 'chain' => []];
        }

        $latest = $claimed->last();
        $headline = "Promoted from a previous class — now in {$latest->next_grade} since {$latest->claimed_at->format('M Y')}.";

        $chain = $claimed->count() > 1
            ? $claimed->map(fn (PromotionRecord $r) => "{$r->releasedFromGrade()} → {$r->next_grade} (claimed {$r->claimed_at->format('M Y')})")->all()
            : [];

        return ['headline' => $headline, 'chain' => $chain];
    }

    /**
     * LearnerHistory_Addition.txt Part 2 — the Learner's ENTIRE lifetime
     * session history, not scoped to the current Teacher or school year,
     * so a new Teacher after a promotion sees where this child actually
     * stands instead of starting blind. Always the real three-tier label,
     * never collapsed into a subjective "Good"/"Weak" word.
     */
    public function proficiencyTrajectorySummary(): string
    {
        $sessions = $this->readingSessions->sortBy('timestamp')->values();

        if ($sessions->isEmpty()) {
            return 'No reading sessions recorded yet. Starting level: '.($this->mastery_level ?? 'New');
        }

        $startedAt = $sessions->first()->level_before ?? 'New';
        $currentlyAt = $sessions->last()->level_after ?? ($this->mastery_level ?? 'New');

        return "Proficiency: started at {$startedAt} → currently {$currentlyAt}";
    }

    /**
     * "TB-48213" style — generated once at creation, retried on the rare
     * collision (learner_code is UNIQUE in the schema).
     */
    public static function generateUniqueCode(): string
    {
        do {
            $code = 'TB-' . random_int(10000, 99999);
        } while (self::where('learner_code', $code)->exists());

        return $code;
    }
}
