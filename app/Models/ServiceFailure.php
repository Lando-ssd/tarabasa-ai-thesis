<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

/**
 * One failed call to a teammate service, kept for the Admin screen (see the migration for why).
 * Writing it must NEVER get in the way of the real work, so record() swallows every error.
 */
class ServiceFailure extends Model
{
    public const SERVICES = ['generator' => 'Activity generator', 'reader' => 'Reading checker', 'recommender' => 'Adaptive recommender', 'mail' => 'Email sending', 'check' => 'Service check'];

    public $timestamps = false;

    protected $fillable = ['service', 'status', 'trail', 'content_type', 'what', 'body'];

    protected $casts = ['created_at' => 'datetime'];

    /** How long a row is kept. Older rows are removed the next time one is written. */
    public const KEEP_DAYS = 14;

    /**
     * @param  ?Response  $response  null when the service never answered at all
     * @param  array<int,int|string>  $trail  every status seen, in order (a retry adds one each time)
     */
    public static function record(string $service, ?Response $response, string $what, array $trail = [], ?string $note = null): void
    {
        try {
            self::create([
                'service' => $service,
                'status' => $response?->status(),
                'trail' => $trail ? mb_substr(implode(',', $trail), 0, 60) : null,
                'content_type' => $response ? mb_substr((string) $response->header('Content-Type'), 0, 80) : null,
                'what' => mb_substr($what, 0, 160),
                'body' => mb_substr($note ?? ($response ? $response->body() : ''), 0, 1500),
            ]);

            self::where('created_at', '<', now()->subDays(self::KEEP_DAYS))->delete();
        } catch (\Throwable $e) {
            Log::warning('Could not write the service failure diary', ['error' => $e->getMessage()]);
        }
    }
}
