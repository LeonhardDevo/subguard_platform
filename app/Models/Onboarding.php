<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Onboarding extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'status',
        'stripe_account_id',
        'stripe_account_email',
        'stripe_onboarding_complete',
        'stripe_capabilities_enabled',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'stripe_onboarding_complete' => 'boolean',
            'stripe_capabilities_enabled' => 'boolean',
            'metadata' => 'array',
        ];
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeStripeConnected($query)
    {
        return $query->where('status', 'stripe_connected');
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Helpers
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isStripeConnected(): bool
    {
        return $this->status === 'stripe_connected' || $this->isCompleted();
    }
}
