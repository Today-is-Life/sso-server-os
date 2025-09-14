<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string|null $email_hash
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string|null $phone
 * @property string|null $phone_hash
 * @property \Illuminate\Support\Carbon|null $phone_verified_at
 * @property string $password
 * @property string|null $mfa_secret
 * @property bool $mfa_enabled
 * @property array<string>|null $mfa_recovery_codes
 * @property string|null $avatar_url
 * @property string|null $locale
 * @property string|null $timezone
 * @property array<string, mixed>|null $preferences
 * @property \Illuminate\Support\Carbon|null $last_login_at
 * @property string|null $last_login_ip
 * @property int $failed_login_attempts
 * @property \Illuminate\Support\Carbon|null $locked_until
 * @property string|null $remember_token
 * @property array<string, mixed>|null $data_restrictions
 * @property bool $marketing_consent
 * @property bool $analytics_consent
 * @property bool $profiling_consent
 * @property \Illuminate\Support\Carbon|null $password_changed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Collection<int, Group> $groups
 * @property-read Collection<int, Permission> $permissions
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, HasUuids, SoftDeletes, HasApiTokens;

    // SSO server uses default connection

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    /**
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'email',
        'email_hash',
        'email_verified_at',
        'phone',
        'phone_hash',
        'phone_verified_at',
        'password',
        'mfa_secret',
        'mfa_enabled',
        'mfa_recovery_codes',
        'avatar_url',
        'locale',
        'timezone',
        'preferences',
        'last_login_at',
        'last_login_ip',
        'failed_login_attempts',
        'locked_until',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'mfa_secret',
        'mfa_recovery_codes',
        'email',
        'phone',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'locked_until' => 'datetime',
            'password_changed_at' => 'datetime',
            'password' => 'hashed',
            'mfa_enabled' => 'boolean',
            'mfa_recovery_codes' => 'array',
            'preferences' => 'array',
            'data_restrictions' => 'array',
            'marketing_consent' => 'boolean',
            'analytics_consent' => 'boolean',
            'profiling_consent' => 'boolean',
        ];
    }

    /**
     * Set the user's email with encryption
     */
    public function setEmailAttribute(?string $value): void
    {
        if ($value) {
            $this->attributes['email'] = Crypt::encryptString($value);
            $this->attributes['email_hash'] = hash('sha256', strtolower($value));
        }
    }

    /**
     * Get the user's decrypted email
     */
    public function getEmailAttribute(?string $value): ?string
    {
        try {
            return $value ? Crypt::decryptString($value) : null;
        } catch (\Exception $e) {
            // Fallback for unencrypted data
            return $value;
        }
    }

    /**
     * Set the user's phone with hashing (temporarily disabled encryption)
     */
    public function setPhoneAttribute(?string $value): void
    {
        if ($value) {
            // TODO: Re-enable encryption after fixing existing data
            $this->attributes['phone'] = $value;
            $this->attributes['phone_hash'] = hash('sha256', $value);
        }
    }

    /**
     * Get the user's phone (temporarily disabled decryption)
     */
    public function getPhoneAttribute(?string $value): ?string
    {
        // TODO: Re-enable decryption after fixing existing data
        return $value;
    }

    /**
     * Set the MFA secret with encryption
     */
    public function setMfaSecretAttribute(?string $value): void
    {
        if ($value) {
            $this->attributes['mfa_secret'] = Crypt::encryptString($value);
        }
    }

    /**
     * Get the decrypted MFA secret
     */
    public function getMfaSecretAttribute(?string $value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    /**
     * Groups relationship
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'user_groups')
            ->withPivot(['assigned_at', 'assigned_by', 'expires_at', 'is_primary'])
            ->where(function($query) {
                $query->where('user_groups.expires_at', '>', now())
                      ->orWhereNull('user_groups.expires_at');
            });
    }

    /**
     * Get all groups for a specific domain
     */
    public function groupsForDomain(string $domainId): Collection
    {
        return $this->groups()->where('domain_id', $domainId)->get();
    }

    /**
     * Check if user has a specific permission
     */
    public function hasPermission(string $permission, ?string $domainId = null): bool
    {
        $groups = $domainId ? $this->groupsForDomain($domainId) : $this->groups;
        
        foreach ($groups as $group) {
            if ($group->hasPermission($permission)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get all permissions for user
     */
    public function getAllPermissions(?string $domainId = null): Collection
    {
        $permissions = collect();
        $groups = $domainId ? $this->groupsForDomain($domainId) : $this->groups;
        
        foreach ($groups as $group) {
            $permissions = $permissions->merge($group->getAllPermissions());
        }
        
        return $permissions->unique('id');
    }

    /**
     * Sessions relationship
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(UserSession::class);
    }

    /**
     * Active sessions
     */
    public function activeSessions(): HasMany
    {
        return $this->sessions()
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now());
    }

    /**
     * Social accounts relationship
     */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /**
     * Audit logs relationship
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Check if account is locked
     */
    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    /**
     * Lock account for specified minutes
     */
    public function lockAccount(int $minutes = 30): void
    {
        $this->update([
            'locked_until' => now()->addMinutes($minutes)
        ]);
    }

    /**
     * Unlock account
     */
    public function unlockAccount(): void
    {
        $this->update([
            'locked_until' => null,
            'failed_login_attempts' => 0
        ]);
    }

    /**
     * Record failed login attempt
     */
    public function recordFailedLogin(): void
    {
        $this->increment('failed_login_attempts');
        
        if ($this->failed_login_attempts >= 5) {
            $this->lockAccount(30);
        }
    }

    /**
     * Record successful login
     */
    public function recordSuccessfulLogin(?string $ip = null): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
            'failed_login_attempts' => 0
        ]);
    }

    /**
     * Generate 2FA secret
     */
    public function generate2FASecret(): string
    {
        $totp = \OTPHP\TOTP::create();
        $secret = $totp->getSecret();

        $this->mfa_secret = $secret;
        $this->save();

        return $secret;
    }

    /**
     * Get 2FA QR Code URL for setup
     */
    public function get2FAQRCodeUrl(): ?string
    {
        if (!$this->mfa_secret) {
            return null;
        }

        $totp = \OTPHP\TOTP::create($this->mfa_secret);
        $totp->setLabel($this->email);
        $totp->setIssuer('SSO Server - Today is Life');

        return $totp->getQrCodeUri(
            'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=',
            urlencode($totp->getProvisioningUri())
        );
    }

    /**
     * Get 2FA provisioning URI for manual setup
     */
    public function get2FAProvisioningUri(): ?string
    {
        if (!$this->mfa_secret) {
            return null;
        }

        $totp = \OTPHP\TOTP::create($this->mfa_secret);
        $totp->setLabel($this->email);
        $totp->setIssuer('SSO Server - Today is Life');

        return $totp->getProvisioningUri();
    }

    /**
     * Verify 2FA token
     */
    public function verify2FAToken(string $token): bool
    {
        if (!$this->mfa_enabled || !$this->mfa_secret) {
            return false;
        }

        try {
            $totp = \OTPHP\TOTP::create($this->mfa_secret);
            $totp->setLabel($this->email);
            $totp->setIssuer('SSO Server - Today is Life');

            // Verify token with a window of 2 periods (60 seconds total) to account for clock drift
            return $totp->verify($token, null, 2);
        } catch (\Exception $e) {
            Log::error('2FA token verification failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Enable 2FA for user
     */
    public function enable2FA(string $verificationToken): bool
    {
        if (!$this->mfa_secret) {
            return false;
        }

        // Verify the setup token before enabling
        if (!$this->verify2FAToken($verificationToken)) {
            return false;
        }

        $this->mfa_enabled = true;
        $this->save();

        // Generate recovery codes
        $this->generateRecoveryCodes();

        Log::info('2FA enabled for user: ' . $this->id);
        return true;
    }

    /**
     * Disable 2FA for user
     */
    public function disable2FA(): bool
    {
        $this->mfa_enabled = false;
        $this->mfa_secret = null;
        $this->mfa_recovery_codes = null;
        $this->save();

        Log::info('2FA disabled for user: ' . $this->id);
        return true;
    }

    /**
     * Generate recovery codes
     */
    public function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4)) . '-' . bin2hex(random_bytes(4)));
        }
        
        $this->mfa_recovery_codes = array_map(fn($code) => Hash::make($code), $codes);
        $this->save();
        
        return $codes;
    }

    /**
     * Use recovery code
     */
    public function useRecoveryCode(string $code): bool
    {
        if (!$this->mfa_recovery_codes) {
            return false;
        }
        
        foreach ($this->mfa_recovery_codes as $index => $hashedCode) {
            if (Hash::check($code, $hashedCode)) {
                $codes = $this->mfa_recovery_codes;
                unset($codes[$index]);
                $this->mfa_recovery_codes = array_values($codes);
                $this->save();
                return true;
            }
        }
        
        return false;
    }


    /**
     * Find user by email
     */
    public static function findByEmail(string $email): ?User
    {
        $emailHash = hash('sha256', strtolower($email));
        return static::where('email_hash', $emailHash)->first();
    }

    /**
     * Find user by phone
     */
    public static function findByPhone(string $phone): ?User
    {
        $phoneHash = hash('sha256', $phone);
        return static::where('phone_hash', $phoneHash)->first();
    }

    /**
     * Create audit log entry
     */
    public function createAuditLog(string $action, ?string $modelType = null, ?string $modelId = null, ?array $oldValues = null, ?array $newValues = null): void
    {
        // TODO: Implement audit log creation when AuditLog model is created
        Log::info('Audit log entry', [
            'user_id' => $this->id,
            'action' => $action,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'severity' => $this->getAuditSeverity($action),
        ]);
    }

    /**
     * Determine audit severity
     */
    private function getAuditSeverity(string $action): string
    {
        $criticalActions = ['delete', 'destroy', 'revoke', 'lock'];
        $warningActions = ['update', 'modify', 'change'];
        
        foreach ($criticalActions as $critical) {
            if (str_contains(strtolower($action), $critical)) {
                return 'critical';
            }
        }
        
        foreach ($warningActions as $warning) {
            if (str_contains(strtolower($action), $warning)) {
                return 'warning';
            }
        }
        
        return 'info';
    }
}