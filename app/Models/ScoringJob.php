<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\File;

/**
 * One recording being scored in the background. See the migration for why this exists.
 *
 * The status only moves forward: queued, running, then done or failed. Taking a job (queued to running) is one
 * atomic update, so the background worker and the page that is waiting for it (when no worker is running) can
 * never both score the same recording.
 */
class ScoringJob extends Model
{
    use HasUuids;

    public const READING = 'reading';

    public const PRACTICE = 'practice';

    public const DIAGNOSTIC = 'diagnostic';

    public const REREAD = 'reread';

    public const KINDS = [self::READING, self::PRACTICE, self::DIAGNOSTIC, self::REREAD];

    public const QUEUED = 'queued';

    public const RUNNING = 'running';

    public const DONE = 'done';

    public const FAILED = 'failed';

    protected $fillable = [
        'id', 'learner_id', 'kind', 'activity_id', 'client_key', 'status', 'audio_path', 'audio_mime', 'audio_name',
        'params', 'outcome', 'error', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'params' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::QUEUED, self::RUNNING], true);
    }

    /** Jobs still waiting or running, and not so old that the worker that had them must have died. */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::QUEUED, self::RUNNING])
            ->where('created_at', '>', now()->subSeconds(self::staleAfter()));
    }

    /** How long a job may stay queued or running before it is given up on (the worker or the whole server was lost). */
    public static function staleAfter(): int
    {
        return (int) config('scoring.stale_after', 240);
    }

    public function isStale(): bool
    {
        return $this->isActive() && $this->created_at->lt(now()->subSeconds(self::staleAfter()));
    }

    /** Take the job: true for exactly one caller. */
    public function claim(): bool
    {
        $taken = static::where('id', $this->id)
            ->where('status', self::QUEUED)
            ->update(['status' => self::RUNNING, 'started_at' => now()]);

        if ($taken) {
            $this->status = self::RUNNING;
        }

        return (bool) $taken;
    }

    public function complete(string $encodedOutcome): void
    {
        $this->forceFill(['status' => self::DONE, 'outcome' => $encodedOutcome, 'error' => null, 'finished_at' => now()])->save();
        $this->discardAudio();
    }

    public function fail(string $message): void
    {
        $this->forceFill(['status' => self::FAILED, 'error' => mb_substr($message, 0, 300), 'finished_at' => now()])->save();
        $this->discardAudio();
    }

    /** The recording is only here to be scored: it goes the moment the job ends, whatever the outcome. */
    public function discardAudio(): void
    {
        if ($this->audio_path) {
            $full = self::audioFolder().DIRECTORY_SEPARATOR.basename($this->audio_path);

            if (File::exists($full)) {
                File::delete($full);
            }

            // Forgotten only once it is really gone (a file that is still open cannot be deleted on Windows): the clean-up
            // in BackgroundScoring::prune() looks for finished jobs that still have one and tries again.
            if (! File::exists($full)) {
                $this->forceFill(['audio_path' => null])->save();
            }
        }
    }

    public function audioFullPath(): ?string
    {
        if (! $this->audio_path) {
            return null;
        }

        $full = self::audioFolder().DIRECTORY_SEPARATOR.basename($this->audio_path);

        return File::exists($full) ? $full : null;
    }

    public static function audioFolder(): string
    {
        return storage_path('app/scoring');
    }
}
