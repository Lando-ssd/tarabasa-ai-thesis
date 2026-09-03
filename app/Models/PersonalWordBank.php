<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonalWordBank extends Model
{
    public $timestamps = false;

    protected $table = 'personal_word_bank';

    protected $fillable = [
        'learner_id',
        'session_id',
        'word',
        'skill_type',
        'mastery_status',
        'times_drilled',
        'last_reviewed',
    ];

    public function learner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function session(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ReadingSession::class, 'session_id');
    }
}
