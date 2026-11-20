<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt to pay for a paid activity in test mode (config/payments.php, App\Services\PaymentService). It is the record, not the money:
 * no card or wallet detail is ever stored here.
 */
class Payment extends Model
{
    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const CANCELLED = 'cancelled';

    public const EXPIRED = 'expired';

    public const FAILED = 'failed';

    protected $fillable = [
        'reference', 'parent_id', 'learner_id', 'listing_id', 'provider', 'provider_session_id', 'checkout_url', 'amount_centavos', 'currency',
        'method', 'provider_payment_id', 'status', 'failure_reason', 'is_test', 'expires_at', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'is_test' => 'boolean',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'amount_centavos' => 'integer',
        ];
    }

    public function parentAccount(): BelongsTo
    {
        return $this->belongsTo(ParentAccount::class, 'parent_id');
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(OpenRepositoryListing::class, 'listing_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    public function isPaid(): bool
    {
        return $this->status === self::PAID;
    }

    /** The price in pesos, for showing (the record keeps centavos so no rounding is ever lost). */
    public function pesos(): float
    {
        return $this->amount_centavos / 100;
    }

    /** The way to pay in words a parent knows. */
    public function methodLabel(): string
    {
        return match ($this->method) {
            'card' => 'Card',
            'gcash' => 'GCash',
            'maya' => 'Maya',
            default => 'Not chosen yet',
        };
    }
}
