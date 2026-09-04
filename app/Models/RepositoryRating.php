<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RepositoryRating extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'listing_id',
        'parent_id',
        'learner_id',
        'rating',
        'comment',
    ];

    public function listing(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(OpenRepositoryListing::class, 'listing_id');
    }

    public function parentAccount(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ParentAccount::class, 'parent_id');
    }

    public function learner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }
}
