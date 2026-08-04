<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;

/**
 * BRD §18.1 — the verification link lives 24 hours and is single use.
 *
 * Only the HASH is stored. A database leak therefore hands out nothing usable, and the raw
 * token exists only in the email that was sent. `consumed_at` is what makes the link single
 * use: a second click on the same link finds it already consumed.
 */
class EmailVerificationToken extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUsable(Builder $query): Builder
    {
        return $query->whereNull('consumed_at')->where('expires_at', '>', now());
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function isUsable(): bool
    {
        return ! $this->isConsumed() && ! $this->isExpired();
    }

    public function matches(string $rawToken): bool
    {
        return Hash::check($rawToken, $this->token_hash);
    }

    public function consume(): void
    {
        $this->forceFill(['consumed_at' => now()])->save();
    }
}
