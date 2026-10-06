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
        'reading_band',
        'assigned_by_teacher_id',
    ];

    protected function casts(): array
    {
        return ['assigned_at' => 'datetime'];
    }

    /**
     * Every assignment that reaches this learner, all sources together: given to the learner
     * directly, given to their class (the whole class, or only the reading group they are in
     * when it carries a reading_band), or given to a focus group their class carries (scoped to
     * the Teacher who assigned it, since two Teachers can reuse the same label).
     *
     * One definition, used by the activity picker and by the access check, so what a child is
     * shown and what a child may open can never disagree.
     */
    public static function reachingLearner(Learner $learner): \Illuminate\Database\Eloquent\Builder
    {
        $group = \App\Support\ReadingLevel::groupOf($learner);

        return static::query()->where(function ($query) use ($learner, $group) {
            $query->where('learner_id', $learner->id);

            // The class id must be checked for null: where('class_id', null) would match every
            // learner-direct and group assignment row, which also have a null class_id.
            if ($learner->class_id !== null) {
                $query->orWhere(function ($classQuery) use ($learner, $group) {
                    $classQuery->where('class_id', $learner->class_id)
                        ->where(function ($band) use ($group) {
                            $band->whereNull('reading_band');
                            if ($group !== null) {
                                $band->orWhere('reading_band', $group);
                            }
                        });
                });
            }

            if ($learner->schoolClass?->group_tag) {
                $query->orWhere(function ($groupQuery) use ($learner) {
                    $groupQuery->where('group_tag', $learner->schoolClass->group_tag)
                        ->where('assigned_by_teacher_id', $learner->schoolClass->teacher_id);
                });
            }
        });
    }

    public function activity():\Illuminate\Database\Eloquent\Relations\BelongsTo
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
