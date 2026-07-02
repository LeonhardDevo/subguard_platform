<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'plan_id',
        'status',
        'start_date',
        'next_billing_date',
        'canceled_at',
        'provider_subscription_id',
        'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'next_billing_date' => 'date',
            'canceled_at' => 'datetime',
        ];
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeTrialing($query)
    {
        return $query->where('status', 'trialing');
    }

    public function scopePastDue($query)
    {
        return $query->where('status', 'past_due');
    }

    public function scopeCanceled($query)
    {
        return $query->where('status', 'canceled');
    }

    // Relationships
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function failedPayments(): HasMany
    {
        return $this->hasMany(FailedPayment::class);
    }

    // Helpers
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isTrialing(): bool
    {
        return $this->status === 'trialing';
    }

    public function isPastDue(): bool
    {
        return $this->status === 'past_due';
    }
}
