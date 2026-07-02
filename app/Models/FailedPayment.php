<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FailedPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_id',
        'payment_id',
        'amount',
        'currency',
        'reason',
        'retry_count',
        'next_retry_at',
        'resolved_at',
        'resolution',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'next_retry_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public const MAX_RETRIES = 3;
    public const RETRY_INTERVALS = [24, 48, 72]; // hours

    // Scopes
    public function scopeUnresolved($query)
    {
        return $query->whereNull('resolved_at');
    }

    public function scopeRetryable($query)
    {
        return $query->unresolved()
            ->where('retry_count', '<', self::MAX_RETRIES)
            ->where('next_retry_at', '<=', now());
    }

    // Relationships
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function getNextRetryInterval(): ?int
    {
        if ($this->retry_count >= self::MAX_RETRIES) {
            return null;
        }
        return self::RETRY_INTERVALS[$this->retry_count] ?? null;
    }
}
