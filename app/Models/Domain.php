<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Domain extends Model
{
    use HasFactory, HasUuids;

    /**
     * The database connection that should be used by the model.
     *
     * @var string
     */
    // SSO server uses default connection

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'display_name',
        'url',
        'is_active',
        'settings',
        'client_id',
        'client_secret',
        'allowed_origins',
        'redirect_uris',
        'logout_redirect_uri',
        'token_lifetime',
        'refresh_token_lifetime',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
        'allowed_origins' => 'array',
        'redirect_uris' => 'array',
        'token_lifetime' => 'integer',
        'refresh_token_lifetime' => 'integer',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<string>
     */
    protected $hidden = [
        'client_secret',
    ];

    /**
     * Boot the model
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($domain) {
            if (!$domain->client_id) {
                $domain->client_id = 'dom_' . Str::random(32);
            }
            if (!$domain->client_secret) {
                $domain->client_secret = Crypt::encryptString(Str::random(64));
            }
        });
    }

    /**
     * Get decrypted client secret
     */
    public function getClientSecretAttribute(?string $value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    /**
     * Set encrypted client secret
     */
    public function setClientSecretAttribute(?string $value): void
    {
        $this->attributes['client_secret'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * Groups relationship
     */
    public function groups(): HasMany
    {
        return $this->hasMany(Group::class)->orderBy('sort_order');
    }

    /**
     * Root groups (no parent)
     */
    public function rootGroups(): HasMany
    {
        return $this->hasMany(Group::class)->whereNull('parent_id')->orderBy('sort_order');
    }

    /**
     * Sessions relationship
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(UserSession::class);
    }

    /**
     * Audit logs relationship
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Magic links relationship
     */
    public function magicLinks(): HasMany
    {
        return $this->hasMany(MagicLink::class);
    }

    /**
     * Check if origin is allowed
     */
    public function isOriginAllowed(string $origin): bool
    {
        if (!$this->allowed_origins || empty($this->allowed_origins)) {
            return false;
        }

        foreach ($this->allowed_origins as $allowed) {
            if ($allowed === '*' || $allowed === $origin) {
                return true;
            }
            
            // Check wildcard subdomains
            if (strpos($allowed, '*.') === 0) {
                $domain = substr($allowed, 2);
                if (strpos($origin, '.' . $domain) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if redirect URI is allowed
     */
    public function isRedirectUriAllowed(string $uri): bool
    {
        if (!$this->redirect_uris || empty($this->redirect_uris)) {
            return false;
        }

        return in_array($uri, $this->redirect_uris);
    }

    /**
     * Generate new client credentials
     */
    public function regenerateCredentials(): array
    {
        $this->client_id = 'dom_' . Str::random(32);
        $this->client_secret = Str::random(64);
        $this->save();

        return [
            'client_id' => $this->client_id,
            'client_secret' => $this->getClientSecretAttribute($this->attributes['client_secret']),
        ];
    }

    /**
     * Get domain statistics
     */
    public function getStatistics(): array
    {
        return [
            'total_groups' => $this->groups()->count(),
            'total_users' => $this->groups()
                ->join('user_groups', 'groups.id', '=', 'user_groups.group_id')
                ->distinct('user_groups.user_id')
                ->count('user_groups.user_id'),
            'active_sessions' => $this->sessions()
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now())
                ->count(),
            'audit_logs_today' => $this->auditLogs()
                ->whereDate('created_at', today())
                ->count(),
        ];
    }
}