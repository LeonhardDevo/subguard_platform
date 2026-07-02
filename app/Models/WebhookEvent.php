<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider',
        'provider_event_id',
        'event_type',
        'payload',
        'status',
        'processed_at',
        'error',
        'attempts',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    // Scopes
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeProcessed($query)
    {
        return $query->where('status', 'processed');
    }

    public function markProcessed(): void
    {
        $this->update([
            'status' => 'processed',
            'processed_at' => now(),
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error' => $error,
            'attempts' => $this->attempts + 1,
        ]);
    }
}
