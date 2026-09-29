<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A Teacher's own named folder of Approved activities ("Bundle 1", "Bundle 2") that can be
 * assigned to one or more classes at once. See the migration's own doc comment for why this is a
 * distinct concept from Activity::$bundle_title (the AI generator's own "bundle" of levels).
 */
class ActivityBundle extends Model
{
    protected $fillable = [
        'teacher_id',
        'name',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(Activity::class, 'activity_bundle_activities')
            ->withPivot('added_at');
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class, 'activity_bundle_classes', 'activity_bundle_id', 'class_id')
            ->withPivot('assigned_at');
    }
}
