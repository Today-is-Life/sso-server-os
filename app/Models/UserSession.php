<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSession extends Model
{
    use HasUuids;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'token_hash',
        'ip_address',
        'user_agent',
        'last_activity',
        'expires_at',
        'revoked_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'last_activity' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, UserSession>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if session is active
     */
    public function isActive(): bool
    {
        return $this->revoked_at === null && 
               $this->expires_at !== null && 
               $this->expires_at->isFuture();
    }
}