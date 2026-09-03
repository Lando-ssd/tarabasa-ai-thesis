<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolClass extends Model
{
    /**
     * Maps to the real 'classes' table from schema.sql.
     * Named SchoolClass (not Class) because 'class' is a reserved word
     * in PHP — same reasoning as ParentAccount for the 'parents' table.
     */
    protected $table = 'classes';

    public $timestamps = false;

    protected $fillable = [
        'teacher_id',
        'name',
        'grade_level',
        'section',
        'group_tag',
        'school_year',
    ];

    public function teacher(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function learners(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Learner::class, 'class_id');
    }

    /**
     * Philippine school years typically run June through March/April, so
     * the "current" year rolls over in June rather than on the calendar
     * new year (SchoolYear_Addition.txt, Part 2).
     */
    public static function currentSchoolYear(): string
    {
        $now = now();
        $startYear = $now->month >= 6 ? $now->year : $now->year - 1;

        return "{$startYear}-" . ($startYear + 1);
    }

    /**
     * Only a year strictly BEFORE the current one is a locked historical
     * record — shared by the index filter and the Edit/Join-Learner
     * backend guards so all three enforce the exact same boundary.
     */
    public static function isYearPast(string $schoolYear): bool
    {
        $yearStart = (int) explode('-', $schoolYear)[0];
        $currentYearStart = (int) explode('-', self::currentSchoolYear())[0];

        return $yearStart < $currentYearStart;
    }
}
