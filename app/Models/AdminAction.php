<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * One thing an Admin did to an account: approved, rejected, reopened, deactivated or activated it, with the reason
 * or note they gave. Kept so the Admin's work can be audited (the Activity log screen).
 *
 * Writing a row must never be the reason a real action fails, so record() swallows its own errors and says so in
 * the log instead.
 */
class AdminAction extends Model
{
    public const ACTIONS = [
        'approved' => 'approved',
        'rejected' => 'rejected',
        'reopened' => 'brought back for review',
        'deactivated' => 'deactivated',
        'activated' => 'activated',
        'resent' => 'sent the verification email again to',
    ];

    public $timestamps = false;

    protected $fillable = ['admin_user_id', 'admin_name', 'action', 'target_user_id', 'target_name', 'target_role', 'detail', 'note'];

    protected $casts = ['created_at' => 'datetime'];

    /**
     * @param  ?string  $detail  a fact worth keeping next to the name, such as the school of a teacher
     * @param  ?string  $note  the reason or note the Admin typed or picked
     */
    public static function record(User $admin, string $action, User $target, ?string $detail = null, ?string $note = null): void
    {
        try {
            self::create([
                'admin_user_id' => $admin->id,
                'admin_name' => mb_substr(trim($admin->first_name.' '.$admin->last_name), 0, 120),
                'action' => $action,
                'target_user_id' => $target->id,
                'target_name' => mb_substr(trim($target->first_name.' '.$target->last_name), 0, 120),
                'target_role' => $target->user_type,
                'detail' => $detail !== null ? mb_substr($detail, 0, 200) : null,
                'note' => $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 200) : null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Could not write the admin activity log', ['error' => $e->getMessage()]);
        }
    }

    /** "approved", "rejected" and the rest as a short phrase for a sentence. */
    public function phrase(): string
    {
        return self::ACTIONS[$this->action] ?? $this->action;
    }
}
