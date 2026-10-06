<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * "Handled": what a Teacher did about a computed alert. See App\Services\TeacherAlerts and the
 * migration for how it hides an alert until new evidence appears.
 */
class TeacherAlertAction extends Model
{
    public $timestamps = false;

    protected $fillable = ['teacher_id', 'learner_id', 'kind', 'handled_at'];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }
}
