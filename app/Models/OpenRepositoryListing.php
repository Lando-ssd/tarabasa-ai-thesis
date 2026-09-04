<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpenRepositoryListing extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'activity_id',
        'teacher_id',
        'price_type',
        'price',
    ];

    public function activity(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function teacher(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function unlocks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RepositoryUnlock::class, 'listing_id');
    }

    public function ratings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RepositoryRating::class, 'listing_id');
    }

    public function isUnlockedFor(int $learnerId): bool
    {
        return $this->unlocks()->where('learner_id', $learnerId)->exists();
    }
}
