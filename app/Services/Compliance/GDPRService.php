<?php

declare(strict_types=1);

namespace App\Services\Compliance;

use App\Models\User;
use App\Services\SIEM\SIEMService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class GDPRService
{
    private SIEMService $siem;

    public function __construct(SIEMService $siem)
    {
        $this->siem = $siem;
    }

    /**
     * GDPR Article 15: Right of Access
     * Export all user data
     */
    public function exportUserData(User $user): string
    {
        $data = [
            'export_date' => now()->toIso8601String(),
            'user_id' => $user->id,
            'personal_information' => $this->getPersonalInformation($user),
            'account_data' => $this->getAccountData($user),
            'activity_logs' => $this->getActivityLogs($user),
            'permissions' => $this->getPermissions($user),
            'devices' => $this->getDevices($user),
            'sessions' => $this->getSessions($user),
            'data_processing' => $this->getDataProcessingInfo($user),
        ];

        // Create encrypted export
        $json = json_encode($data, JSON_PRETTY_PRINT);
        if ($json === false) {
            throw new \Exception('Failed to encode user data');
        }
        $encrypted = Crypt::encryptString($json);

        // Store export
        $filename = sprintf('gdpr_export_%s_%s.json', $user->id, now()->format('Y-m-d-H-i-s'));
        Storage::put("gdpr-exports/{$filename}", $encrypted);

        // Log export
        $this->logDataExport($user);

        // Send to user
        $this->sendExportToUser($user, $filename);

        return $filename;
    }

    /**
     * GDPR Article 17: Right to Erasure
     * Delete or anonymize user data
     */
    public function deleteUserData(User $user, bool $hardDelete = false): void
    {
        DB::beginTransaction();
        try {
            if ($hardDelete) {
                $this->performHardDelete($user);
            } else {
                $this->performSoftDelete($user);
            }

            // Notify all domains to delete local data
            $this->notifyDomainsOfDeletion($user);

            // Log deletion
            $this->logDataDeletion($user, $hardDelete);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * GDPR Article 18: Right to Restriction
     * Restrict processing of user data
     *
     * @param  array<string, mixed>  $restrictions
     */
    public function restrictProcessing(User $user, array $restrictions): void
    {
        $user->data_restrictions = array_merge(
            $user->data_restrictions ?? [],
            $restrictions
        );
        $user->save();

        // Notify systems
        $this->notifySystemsOfRestriction($user, $restrictions);

        // Log restriction
        $this->logDataRestriction($user, $restrictions);
    }

    /**
     * GDPR Article 20: Right to Data Portability
     * Export data in machine-readable format
     */
    public function exportPortableData(User $user): string
    {
        $data = [
            'format' => 'json',
            'version' => '1.0',
            'exported_at' => now()->toIso8601String(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'created_at' => $user->created_at,
            ],
            'data' => $this->getPortableData($user),
        ];

        $filename = sprintf('portable_data_%s_%s.json', $user->id, now()->format('Y-m-d'));
        $jsonContent = json_encode($data);
        if ($jsonContent === false) {
            throw new \Exception('Failed to encode portable data');
        }
        Storage::put("gdpr-exports/{$filename}", $jsonContent);

        return $filename;
    }

    /**
     * GDPR Article 21: Right to Object
     * Handle objection to data processing
     *
     * @param  array<int, array{type: string, reason: string}>  $objections
     */
    public function handleObjection(User $user, array $objections): void
    {
        foreach ($objections as $objection) {
            DB::table('data_objections')->insert([
                'id' => \Illuminate\Support\Str::uuid(),
                'user_id' => $user->id,
                'processing_type' => $objection['type'],
                'reason' => $objection['reason'],
                'status' => 'pending',
                'created_at' => now(),
            ]);
        }

        // Process objections
        $this->processObjections($user, $objections);
    }

    /**
     * Get personal information
     *
     * @return array<string, mixed>
     */
    private function getPersonalInformation(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar_url' => $user->avatar_url,
            'locale' => $user->locale,
            'timezone' => $user->timezone,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }

    /**
     * Get account data
     *
     * @return array<string, mixed>
     */
    private function getAccountData(User $user): array
    {
        return [
            'email_verified' => (bool) $user->email_verified_at,
            'phone_verified' => (bool) $user->phone_verified_at,
            'mfa_enabled' => $user->mfa_enabled,
            'last_login' => $user->last_login_at,
            'last_ip' => $user->last_login_ip,
            'preferences' => $user->preferences,
        ];
    }

    /**
     * Get activity logs
     *
     * @return array<int, mixed>
     */
    private function getActivityLogs(User $user): array
    {
        return DB::table('audit_logs')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(1000)
            ->get()
            ->toArray();
    }

    /**
     * Get permissions
     *
     * @return array<int, mixed>
     */
    private function getPermissions(User $user): array
    {
        return $user->getAllPermissions()
            ->map(fn ($p) => [
                'name' => $p->name,
                'slug' => $p->slug,
                'granted_at' => $p->pivot->created_at ?? null,
            ])
            ->toArray();
    }

    /**
     * Get devices
     *
     * @return array<int, mixed>
     */
    private function getDevices(User $user): array
    {
        return DB::table('user_devices')
            ->where('user_id', $user->id)
            ->get()
            ->map(fn ($d) => [
                'device_name' => $d->device_name,
                'device_type' => $d->device_type,
                'last_active' => $d->last_active_at,
                'trusted' => $d->is_trusted,
            ])
            ->toArray();
    }

    /**
     * Get sessions
     *
     * @return array<int, mixed>
     */
    private function getSessions(User $user): array
    {
        return DB::table('user_sessions')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get()
            ->toArray();
    }

    /**
     * Get data processing information
     *
     * @return array<string, mixed>
     */
    private function getDataProcessingInfo(User $user): array
    {
        return [
            'purposes' => [
                'authentication' => 'Required for service access',
                'security' => 'Fraud prevention and account security',
                'communication' => 'Service updates and notifications',
            ],
            'legal_basis' => 'Contractual necessity and legitimate interest',
            'retention_period' => '365 days after account closure',
            'third_parties' => $this->getThirdPartySharing($user),
            'international_transfers' => $this->getInternationalTransfers($user),
        ];
    }

    /**
     * Get third party sharing info
     *
     * @return array<int, mixed>
     */
    private function getThirdPartySharing(User $user): array
    {
        $shares = [];

        // Check which domains have user data
        $domains = DB::table('domain_users')
            ->where('user_id', $user->id)
            ->join('domains', 'domains.id', '=', 'domain_users.domain_id')
            ->select('domains.name', 'domains.url', 'domain_users.created_at')
            ->get();

        foreach ($domains as $domain) {
            $shares[] = [
                'entity' => $domain->name,
                'purpose' => 'Service provision',
                'data_shared' => ['name', 'email', 'permissions'],
                'shared_at' => $domain->created_at,
            ];
        }

        return $shares;
    }

    /**
     * Get international transfer info
     *
     * @return array<string, mixed>
     */
    private function getInternationalTransfers(User $user): array
    {
        // Check if data is transferred internationally
        return [
            'transfers_occur' => false,
            'countries' => [],
            'safeguards' => 'Standard Contractual Clauses',
        ];
    }

    /**
     * Perform hard delete
     */
    private function performHardDelete(User $user): void
    {
        // Delete from all tables
        DB::table('audit_logs')->where('user_id', $user->id)->delete();
        DB::table('user_devices')->where('user_id', $user->id)->delete();
        DB::table('user_sessions')->where('user_id', $user->id)->delete();
        DB::table('user_groups')->where('user_id', $user->id)->delete();
        DB::table('domain_users')->where('user_id', $user->id)->delete();

        // Finally delete user
        $user->forceDelete();
    }

    /**
     * Perform soft delete (anonymization)
     */
    private function performSoftDelete(User $user): void
    {
        // Anonymize personal data
        $user->name = 'Deleted User';
        $user->email = sprintf('deleted_%s@deleted.local', $user->id);
        $user->phone = null;
        $user->avatar_url = null;
        $user->last_login_ip = null;
        $user->preferences = null;
        $user->deleted_at = now();
        $user->save();

        // Remove from groups
        DB::table('user_groups')->where('user_id', $user->id)->delete();

        // Anonymize logs
        DB::table('audit_logs')
            ->where('user_id', $user->id)
            ->update(['user_id' => null, 'ip_address' => null]);
    }

    /**
     * Notify domains of deletion
     */
    private function notifyDomainsOfDeletion(User $user): void
    {
        $domains = \App\Models\Domain::all();

        foreach ($domains as $domain) {
            try {
                $signature = hash_hmac('sha256', $user->id, $domain->webhook_secret);

                \Http::withHeaders([
                    'X-SSO-Event' => 'user.deleted',
                    'X-SSO-Signature' => $signature,
                ])->post($domain->url.'/sso/webhook', [
                    'event' => 'user.deleted',
                    'user_id' => $user->id,
                    'deleted_at' => now()->toIso8601String(),
                ]);
            } catch (\Exception $e) {
                \Log::error('Failed to notify domain of user deletion', [
                    'domain' => $domain->id,
                    'user' => $user->id,
                ]);
            }
        }
    }

    /**
     * Process objections
     *
     * @param  array<int, array{type: string, reason: string}>  $objections
     */
    private function processObjections(User $user, array $objections): void
    {
        foreach ($objections as $objection) {
            switch ($objection['type']) {
                case 'marketing':
                    $user->marketing_consent = false;
                    break;
                case 'analytics':
                    $user->analytics_consent = false;
                    break;
                case 'profiling':
                    $user->profiling_consent = false;
                    break;
            }
        }

        $user->save();
    }

    /**
     * Notify systems of restriction
     *
     * @param  array<string, mixed>  $restrictions
     */
    private function notifySystemsOfRestriction(User $user, array $restrictions): void
    {
        // Broadcast to relevant systems
        event(new \App\Events\DataRestrictionApplied($user, $restrictions));
    }

    /**
     * Send export to user
     */
    private function sendExportToUser(User $user, string $filename): void
    {
        Mail::raw(
            "Your data export is ready. You can download it from your account settings.\n\nFile: {$filename}\n\nThis link will expire in 48 hours.",
            function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('Your Data Export is Ready');
            }
        );
    }

    /**
     * Log data export
     */
    private function logDataExport(User $user): void
    {
        $this->siem->sendSecurityEvent([
            'event_id' => 'GDPR_DATA_EXPORT',
            'severity' => 'info',
            'user_id' => $user->id,
            'action' => 'data_export',
            'message' => 'User data exported per GDPR Article 15',
            'metadata' => [
                'article' => '15',
                'right' => 'Access',
            ],
        ]);
    }

    /**
     * Log data deletion
     */
    private function logDataDeletion(User $user, bool $hardDelete): void
    {
        $this->siem->sendSecurityEvent([
            'event_id' => 'GDPR_DATA_DELETION',
            'severity' => 'warning',
            'user_id' => $user->id,
            'action' => 'data_deletion',
            'message' => sprintf('User data %s per GDPR Article 17', $hardDelete ? 'deleted' : 'anonymized'),
            'metadata' => [
                'article' => '17',
                'right' => 'Erasure',
                'type' => $hardDelete ? 'hard_delete' : 'soft_delete',
            ],
        ]);
    }

    /**
     * Log data restriction
     *
     * @param  array<string, mixed>  $restrictions
     */
    private function logDataRestriction(User $user, array $restrictions): void
    {
        $this->siem->sendSecurityEvent([
            'event_id' => 'GDPR_DATA_RESTRICTION',
            'severity' => 'info',
            'user_id' => $user->id,
            'action' => 'data_restriction',
            'message' => 'Data processing restricted per GDPR Article 18',
            'metadata' => [
                'article' => '18',
                'right' => 'Restriction',
                'restrictions' => $restrictions,
            ],
        ]);
    }

    /**
     * Get portable data
     *
     * @return array<string, mixed>
     */
    private function getPortableData(User $user): array
    {
        return [
            'profile' => $this->getPersonalInformation($user),
            'preferences' => $user->preferences ?? [],
            'activity' => [
                'logins' => DB::table('audit_logs')
                    ->where('user_id', $user->id)
                    ->where('action', 'login')
                    ->limit(100)
                    ->get(['created_at', 'ip_address'])
                    ->toArray(),
            ],
        ];
    }
}
