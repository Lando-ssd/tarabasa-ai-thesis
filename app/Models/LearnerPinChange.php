<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One time a guardian set a new PIN for a child. Never holds the PIN itself. */
class LearnerPinChange extends Model
{
    public $timestamps = false;

    protected $fillable = ['learner_id', 'user_id', 'method', 'changed_at'];

    protected function casts(): array
    {
        return ['changed_at' => 'datetime'];
    }

    public function learner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }
}
