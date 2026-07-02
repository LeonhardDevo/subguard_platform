<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'company_name',
        'role',
        'stripe_customer_id',
        'is_locked',
        'subscriber_limit',
        'two_factor_secret',
        'two_factor_enabled',
        'two_factor_confirmed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
            'is_locked' => 'boolean',
            'subscriber_limit' => 'integer',
            'two_factor_enabled' => 'boolean',
        ];
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_enabled && ! empty($this->two_factor_secret);
    }

    public function enableTwoFactor(string $secret): void
    {
        $this->update([
            'two_factor_secret' => encrypt($secret),
            'two_factor_enabled' => true,
        ]);
    }

    public function disableTwoFactor(): void
    {
        $this->update([
            'two_factor_secret' => null,
            'two_factor_enabled' => false,
            'two_factor_confirmed_at' => null,
        ]);
    }

    public function confirmTwoFactor(): void
    {
        $this->update([
            'two_factor_confirmed_at' => now(),
        ]);
    }

    // JWT Methods
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'role' => $this->role,
            'company' => $this->company_name,
        ];
    }

    // Relationships
    public function customers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function plans(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Plan::class);
    }

    public function subscriptions(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(Subscription::class, Customer::class);
    }

    public function auditLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function onboarding(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Onboarding::class);
    }
}
