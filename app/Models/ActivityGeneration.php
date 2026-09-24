<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A "Generate activities" request, written in the background (see GenerateActivitiesJob and the
 * activity_generations migration). The Activities page follows its status.
 */
class ActivityGeneration extends Model
{
    public const QUEUED = 'Queued';

    public const RUNNING = 'Running';

    public const DONE = 'Done';

    public const FAILED = 'Failed';

    /**
     * A request that has been Queued or Running longer than this has been lost (the server
     * restarted mid-way, for example). It is closed as Failed so it can never block the teacher.
     * The longest real request (5 of each level) takes about 7 minutes.
     */
    public const LOST_AFTER_MINUTES = 20;

    /**
     * Seconds a request may sit Queued before the page that is watching it runs it itself, in
     * case the background worker is not running.
     */
    public const PICKUP_WAIT_SECONDS = 45;

    /**
     * How long a request takes, measured against the live service (2026-09-24, service awake):
     * 1 of each level took 80 and 122 seconds, 3 of each level took 122 seconds. So most of the
     * wait is a fixed cost per request, and each extra text in a level adds a little. A service
     * that has been asleep adds up to a minute more. Used only to tell the teacher what to expect.
     */
    public const BASE_SECONDS = 90;

    public const EXTRA_SECONDS_PER_TEXT = 20;

    protected $fillable = [
        'teacher_id', 'status', 'grade_level', 'competency', 'activity_type', 'topic',
        'teacher_notes', 'levels', 'created_count', 'message', 'started_at', 'finished_at', 'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'levels' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::QUEUED, self::RUNNING], true);
    }

    /** How many activities the teacher asked for. */
    public function total(): int
    {
        return (int) array_sum($this->levels ?? []);
    }

    /** "2 Medium, 1 Hard", leaving out the levels that were not asked for. */
    public function summary(): string
    {
        return collect($this->levels ?? [])->filter()->map(fn ($n, $tier) => "{$n} {$tier}")->implode(', ');
    }

    /** About how many seconds a request takes when it asks for at most `$mostInOneLevel` of any level. */
    public static function estimateSeconds(int $mostInOneLevel): int
    {
        return self::BASE_SECONDS + self::EXTRA_SECONDS_PER_TEXT * max(0, $mostInOneLevel - 1);
    }

    /** Close any request that has been lost, then say which one is still working, if any. */
    public static function activeFor(int $teacherId): ?self
    {
        self::closeLost($teacherId);

        return self::where('teacher_id', $teacherId)->whereIn('status', [self::QUEUED, self::RUNNING])->latest('id')->first();
    }

    /**
     * What the Activities page should show: the request that is still working, or the last one
     * that finished and the teacher has not seen yet.
     */
    public static function noticeFor(int $teacherId): ?self
    {
        return self::activeFor($teacherId)
            ?? self::where('teacher_id', $teacherId)->whereNull('acknowledged_at')->latest('id')->first();
    }

    /** Close, as Failed, any request of this teacher that is too old to still be working. */
    private static function closeLost(int $teacherId): void
    {
        self::where('teacher_id', $teacherId)
            ->whereIn('status', [self::QUEUED, self::RUNNING])
            ->where('created_at', '<', now()->subMinutes(self::LOST_AFTER_MINUTES))
            ->update([
                'status' => self::FAILED,
                'message' => 'This one did not finish. Nothing was charged. Please try again.',
                'finished_at' => now(),
            ]);
    }

    /** What the page needs to draw this request: read by the status route. */
    public function toStatus(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'active' => $this->isActive(),
            'message' => $this->message,
            'total' => $this->total(),
            'summary' => $this->summary(),
            'startedAt' => ($this->started_at ?? $this->created_at)?->timestamp,
        ];
    }
}
