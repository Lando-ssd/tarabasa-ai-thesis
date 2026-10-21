<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One tick of a consent box: who, for which child (if any), what, which version of the legal pages, and when. */
class ConsentRecord extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'learner_id', 'kind', 'policy_version', 'channel', 'accepted_at', 'withdrawn_at'];

    protected function casts(): array
    {
        return ['accepted_at' => 'datetime', 'withdrawn_at' => 'datetime'];
    }
}
