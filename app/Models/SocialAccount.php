<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialAccount extends Model
{
    use HasUuids;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'provider',
        'provider_user_id',
        'access_token',
        'refresh_token',
        'expires_at',
        'metadata',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * @var array<int, string>
     */
    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    /**
     * @return BelongsTo<User, SocialAccount>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}