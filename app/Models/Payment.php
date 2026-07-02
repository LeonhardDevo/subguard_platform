<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_id',
        'amount',
        'commission_amount',
        'commission_rate',
        'currency',
        'status',
        'provider',
        'provider_payment_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'commission_rate' => 'decimal:4',
            'metadata' => 'array',
        ];
    }

    // Scopes
    public function scopeSucceeded($query)
    {
        return $query->where('status', 'succeeded');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    // Relationships
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }
}
