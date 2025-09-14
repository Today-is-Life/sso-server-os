<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SecurityEvent extends Model
{
    use HasUuids;

    protected $table = 'security_events';

    public $timestamps = false;

    protected $fillable = [
        'event_id',
        'severity',
        'user_id',
        'domain_id',
        'ip_address',
        'action',
        'message',
        'metadata',
        'correlation_id',
        'created_at'
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime'
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->created_at = $model->created_at ?? now();
        });
    }

    /**
     * Get the user that owns the security event
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
