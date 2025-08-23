<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DomainPermissionSyncService
{
    /**
     * Sync permissions from a domain
     */
    public function syncDomainPermissions(Domain $domain): array
    {
        try {
            // Build the discovery endpoint URL (HTTPS enforced)
            $baseUrl = str_replace('http://', 'https://', rtrim($domain->url, '/'));
            $discoveryUrl = $baseUrl.'/api/sso/permissions';

            // Generate signature for request
            $timestamp = time();
            $nonce = \Str::random(32);
            $payload = $domain->client_id.'|'.$timestamp.'|'.$nonce;
            $signature = hash_hmac('sha256', $payload, $domain->client_secret);

            // Make request with encrypted credentials
            $response = Http::withOptions([
                'verify' => true, // Verify SSL certificate
                'ssl_version' => CURL_SSLVERSION_TLSv1_2, // Minimum TLS 1.2
            ])->withHeaders([
                'X-SSO-Client-ID' => $domain->client_id,
                'X-SSO-Timestamp' => $timestamp,
                'X-SSO-Nonce' => $nonce,
                'X-SSO-Signature' => $signature,
                'Accept' => 'application/json',
            ])->timeout(10)->get($discoveryUrl);

            if (! $response->successful()) {
                throw new \Exception('Failed to fetch permissions from domain: '.$response->status());
            }

            $remotePermissions = $response->json('permissions', []);

            $synced = 0;
            $created = 0;
            $updated = 0;

            foreach ($remotePermissions as $permData) {
                // Validate required fields
                if (empty($permData['slug']) || empty($permData['name'])) {
                    Log::warning("Invalid permission data from domain {$domain->name}", $permData);

                    continue;
                }

                // Create a domain-specific slug
                $domainSlug = $domain->name.'.'.$permData['slug'];

                // Find or create permission
                $permission = Permission::firstOrNew([
                    'slug' => $domainSlug,
                ]);

                $isNew = ! $permission->exists;

                // Update permission data
                $permission->fill([
                    'name' => '['.$domain->display_name.'] '.$permData['name'],
                    'description' => $permData['description'] ?? null,
                    'module' => $permData['module'] ?? $domain->name,
                    'category' => $permData['category'] ?? 'domain',
                    'is_system' => false,
                    'metadata' => array_merge($permData['metadata'] ?? [], [
                        'domain_id' => $domain->id,
                        'domain_name' => $domain->name,
                        'original_slug' => $permData['slug'],
                        'synced_at' => now()->toDateTimeString(),
                    ]),
                ]);

                $permission->save();

                if ($isNew) {
                    $created++;
                } else {
                    $updated++;
                }
                $synced++;
            }

            // Log the sync
            DB::table('audit_logs')->insert([
                'id' => \Str::uuid(),
                'user_id' => auth()->id(),
                'action' => 'domain.permissions.sync',
                'model' => Domain::class,
                'model_id' => $domain->id,
                'data' => json_encode([
                    'domain' => $domain->name,
                    'synced' => $synced,
                    'created' => $created,
                    'updated' => $updated,
                ]),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);

            return [
                'success' => true,
                'synced' => $synced,
                'created' => $created,
                'updated' => $updated,
                'message' => "Erfolgreich {$synced} Berechtigungen synchronisiert ({$created} neu, {$updated} aktualisiert)",
            ];

        } catch (\Exception $e) {
            Log::error("Failed to sync permissions for domain {$domain->name}: ".$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Fehler beim Synchronisieren: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Sync permissions for all active domains
     */
    public function syncAllDomains(): array
    {
        $domains = Domain::where('is_active', true)->get();
        $results = [];

        foreach ($domains as $domain) {
            $results[$domain->name] = $this->syncDomainPermissions($domain);
        }

        return $results;
    }

    /**
     * Remove orphaned domain permissions
     */
    public function cleanupOrphanedPermissions(): int
    {
        // Find permissions with domain metadata but no matching domain
        $orphaned = Permission::where('is_system', false)
            ->whereJsonContains('metadata->domain_id', function ($query) {
                $query->whereNotIn('metadata->domain_id', Domain::pluck('id'));
            })
            ->delete();

        return $orphaned;
    }
}
