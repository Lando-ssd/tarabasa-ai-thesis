<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityAssignment extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'activity_id',
        'learner_id',
        'class_id',
        'group_tag',
        'assigned_by_teacher_id',
    ];

    public function activity(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function learner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function schoolClass(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function teacher(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'assigned_by_teacher_id');
    }
}
