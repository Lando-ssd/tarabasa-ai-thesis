<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    public $timestamps = false;

    public const TYPE_SESSION_SUMMARY = 'Session Summary';

    public const TYPE_NEEDS_ATTENTION = 'Needs Attention';

    public const TYPE_LEVEL_CONFIRMED = 'Level Confirmed';

    /** A parent tells every guardian when someone new is linked to their child, and when a teacher adds the child to a class. */
    public const TYPE_GUARDIAN_LINKED = 'Guardian Linked';

    public const TYPE_CLASS_JOINED = 'Class Joined';

    /** A guardian set a new PIN for the child (told to the OTHER guardians), and a child was locked out after five wrong PINs (told to every guardian). */
    public const TYPE_PIN_CHANGED = 'PIN Changed';

    public const TYPE_PIN_LOCKED = 'PIN Locked';

    /** A guardian left a child (told to the guardians who stay), and a child's profile was deleted by the parent (told to the class teacher, without a name). */
    public const TYPE_GUARDIAN_REMOVED = 'Guardian Removed';

    public const TYPE_LEARNER_REMOVED = 'Learner Removed';

    /** The Admin's answer to a teacher who asked to handle another grade (told to that teacher only). */
    public const TYPE_GRADES_APPROVED = 'Grades Approved';

    public const TYPE_GRADES_DECLINED = 'Grades Declined';

    protected $fillable = [
        'recipient_user_id',
        'learner_id',
        'type',
        'message',
        'is_read',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'timestamp' => 'datetime',
        ];
    }

    public function recipient(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function learner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    /**
     * Shared by every real event that notifies people about a Learner —
     * Learner Actor Prompt Step 6 (session summary + urgent-if-flagged,
     * from LearnerReadingController) and PlacementDiagnostic_Addition.txt
     * Step 7 (level-confirmed, from LearnerDiagnosticController) — one
     * place resolving "who actually needs to hear about this Learner,"
     * so those two call sites can't drift into two different answers.
     * $includeTeacher is false for the diagnostic case (Step 7 says
     * Parent(s) only — no Teacher is involved in a diagnostic at all).
     */
    public static function notifyForLearner(Learner $learner, string $type, string $message, bool $includeTeacher = true, ?int $exceptUserId = null): void
    {
        $recipientUserIds = collect();

        if ($includeTeacher && $learner->class_id !== null) {
            $teacherUserId = $learner->schoolClass?->teacher?->user_id;
            if ($teacherUserId) {
                $recipientUserIds->push($teacherUserId);
            }
        }

        $recipientUserIds = $recipientUserIds->concat(
            $learner->parents()->get()->pluck('user_id')
        );

        foreach ($recipientUserIds->unique() as $userId) {
            // The person who did the thing does not need telling about it.
            if ($exceptUserId !== null && (int) $userId === $exceptUserId) {
                continue;
            }

            self::create([
                'recipient_user_id' => $userId,
                'learner_id' => $learner->id,
                'type' => $type,
                'message' => $message,
            ]);
        }
    }
}
