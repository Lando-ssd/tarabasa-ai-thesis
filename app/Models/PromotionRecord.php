<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromotionRecord extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'learner_id',
        'released_by_teacher_id',
        'claimed_by_teacher_id',
        'next_grade',
        'status',
        'released_from_class_id',
        'claimed_into_class_id',
        'claimed_at',
    ];

    protected function casts(): array
    {
        return [
            'released_at' => 'datetime',
            'claimed_at' => 'datetime',
        ];
    }

    public function learner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function releasedByTeacher(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'released_by_teacher_id');
    }

    public function claimedByTeacher(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'claimed_by_teacher_id');
    }

    public function releasedFromClass(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'released_from_class_id');
    }

    public function claimedIntoClass(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'claimed_into_class_id');
    }

    /**
     * "Released from Grade X" for display — computed from `next_grade`
     * (always exactly one grade above where a Learner came from) rather
     * than trusting `releasedFromClass->grade_level`, since a class's own
     * grade_level can in principle drift from what the Learner's grade
     * actually was at release time. next_grade is only ever 'Grade 2' or
     * 'Grade 3' (nobody promotes into Grade 1), so this is always
     * well-defined.
     */
    public function releasedFromGrade(): string
    {
        $nextGradeNumber = (int) substr($this->next_grade, 6);

        return 'Grade '.($nextGradeNumber - 1);
    }
}
