<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParentAccount extends Model
{
    /**
     * Maps to the real 'parents' table from schema.sql.
     * Named ParentAccount (not Parent) because 'Parent' is a
     * reserved word in PHP.
     */
    protected $table = 'parents';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function learners(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Learner::class, 'parent_learners', 'parent_id', 'learner_id')
            ->withPivot(['relationship', 'is_creator', 'linked_at']);
    }
}
