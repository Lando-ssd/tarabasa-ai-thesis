<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class Teacher extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'school_name',
        'employee_id',
        'status',
        'free_generation_credits_remaining',
        'grades_handled',
        'rejection_reason',
        'grades_requested',
        'grades_requested_at',
        'grades_request_note',
    ];

    protected function casts(): array
    {
        // The grades this Teacher handles, e.g. ["Grade 1"] or ["Grade 1","Grade 2"] (one class per grade, never mixed).
        // null means every grade (accounts made before this was asked).
        return ['grades_handled' => 'array', 'grades_requested' => 'array', 'grades_requested_at' => 'datetime'];
    }

    /**
     * Validation rules for the "which grades do you handle" question, shared by sign up and
     * Profile. The mode (one grade, or several grades, a separate class for each) and the ticked grades are
     * both checked on the server, never only in the form.
     */
    public static function gradeRules(): array
    {
        return [
            'grades_mode' => ['required', Rule::in(['single', 'multi'])],
            'grades_handled' => ['required', 'array', 'min:1'],
            'grades_handled.*' => ['string', Rule::in(['Grade 1', 'Grade 2', 'Grade 3'])],
        ];
    }

    /** One grade means exactly one; "more than one" means two or more. Returns the grades in order, no repeats. */
    public static function gradesFromValidated(array $validated): array
    {
        $grades = array_values(array_intersect(['Grade 1', 'Grade 2', 'Grade 3'], $validated['grades_handled']));

        if ($validated['grades_mode'] === 'single' && count($grades) !== 1) {
            throw ValidationException::withMessages(['grades_handled' => 'Pick the one grade you handle.']);
        }

        if ($validated['grades_mode'] === 'multi' && count($grades) < 2) {
            throw ValidationException::withMessages(['grades_handled' => 'Tick at least two grades, or choose One grade.']);
        }

        return $grades;
    }

    /** Grades this Teacher may open a class for. */
    public function gradesAllowed(): array
    {
        $all = ['Grade 1', 'Grade 2', 'Grade 3'];

        return $this->grades_handled ? array_values(array_intersect($all, $this->grades_handled)) : $all;
    }

    /** Grades the Admin has not confirmed yet: what an approved teacher asked to add, or an empty list when nothing is open. */
    public function gradesPending(): array
    {
        return $this->grades_requested ? array_values(array_intersect(['Grade 1', 'Grade 2', 'Grade 3'], $this->grades_requested)) : [];
    }

    /**
     * The Admin's latest answer about grades, kept as a notice for 30 days, so a teacher who comes back to Profile (website or phone) can see
     * what was decided and why. Null when there is none.
     */
    public function latestGradeDecision(): ?array
    {
        $n = Notification::where('recipient_user_id', $this->user_id)
            ->whereIn('type', [Notification::TYPE_GRADES_APPROVED, Notification::TYPE_GRADES_DECLINED])
            ->where('timestamp', '>=', now()->subDays(30))
            ->orderByDesc('id')
            ->first();

        return $n ? ['approved' => $n->type === Notification::TYPE_GRADES_APPROVED, 'message' => $n->message, 'at' => $n->timestamp?->toIso8601String()] : null;
    }

    /** The open request for more grades as a small array for the phone app and the screens, or null. */
    public function gradeRequestPayload(): ?array
    {
        if ($this->gradesPending() === []) {
            return null;
        }

        return [
            'grades' => $this->gradesPending(),
            'added' => array_values(array_diff($this->gradesPending(), $this->gradesAllowed())),
            'note' => $this->grades_request_note,
            'requestedAt' => $this->grades_requested_at?->toIso8601String(),
        ];
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function classes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }
}
