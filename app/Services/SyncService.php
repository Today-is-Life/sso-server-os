<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncService
{
    /**
     * Sync user from SSO to local database
     */
    public function syncUserToLocal($ssoUserId)
    {
        try {
            // Get user from SSO database
            $ssoUser = User::on('sso')->find($ssoUserId);
            
            if (!$ssoUser) {
                return false;
            }
            
            // Find or create user in local database
            $localUser = DB::connection('mysql')->table('users')
                ->where('id', $ssoUser->id)
                ->first();
            
            $userData = [
                'id' => $ssoUser->id,
                'name' => $ssoUser->name,
                'email_hash' => $ssoUser->email_hash,
                'email_verified_at' => $ssoUser->email_verified_at,
                'avatar_url' => $ssoUser->avatar_url,
                'locale' => $ssoUser->locale,
                'timezone' => $ssoUser->timezone,
                'updated_at' => now(),
            ];
            
            if (!$localUser) {
                $userData['created_at'] = now();
                DB::connection('mysql')->table('users')->insert($userData);
            } else {
                DB::connection('mysql')->table('users')
                    ->where('id', $ssoUser->id)
                    ->update($userData);
            }
            
            // Sync user groups for this domain
            $this->syncUserGroups($ssoUser);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('User sync failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Sync user groups for current domain
     */
    public function syncUserGroups($ssoUser)
    {
        $currentDomainId = session('current_domain_id');
        
        if (!$currentDomainId) {
            // Try to detect domain from URL
            $domain = DB::connection('sso')->table('domains')
                ->where('url', 'LIKE', '%' . request()->getHost() . '%')
                ->first();
            
            if ($domain) {
                $currentDomainId = $domain->id;
            }
        }
        
        if (!$currentDomainId) {
            return;
        }
        
        // Get user groups for this domain from SSO
        $userGroups = DB::connection('sso')->table('user_groups')
            ->join('groups', 'user_groups.group_id', '=', 'groups.id')
            ->where('user_groups.user_id', $ssoUser->id)
            ->where('groups.domain_id', $currentDomainId)
            ->where(function ($query) {
                $query->whereNull('user_groups.expires_at')
                    ->orWhere('user_groups.expires_at', '>', now());
            })
            ->select('user_groups.*', 'groups.domain_id')
            ->get();
        
        // Sync to local database
        DB::connection('mysql')->table('user_groups')
            ->where('user_id', $ssoUser->id)
            ->delete();
        
        foreach ($userGroups as $userGroup) {
            DB::connection('mysql')->table('user_groups')->insert([
                'user_id' => $userGroup->user_id,
                'group_id' => $userGroup->group_id,
                'assigned_at' => $userGroup->assigned_at,
                'assigned_by' => $userGroup->assigned_by,
                'expires_at' => $userGroup->expires_at,
                'is_primary' => $userGroup->is_primary,
            ]);
        }
    }
    
    /**
     * Sync groups for a domain
     */
    public function syncDomainGroups($domainId)
    {
        // Get all groups for domain from SSO
        $groups = DB::connection('sso')->table('groups')
            ->where('domain_id', $domainId)
            ->get();
        
        foreach ($groups as $group) {
            $localGroup = DB::connection('mysql')->table('groups')
                ->where('id', $group->id)
                ->first();
            
            $groupData = (array) $group;
            
            if (!$localGroup) {
                DB::connection('mysql')->table('groups')->insert($groupData);
            } else {
                unset($groupData['created_at']);
                DB::connection('mysql')->table('groups')
                    ->where('id', $group->id)
                    ->update($groupData);
            }
        }
        
        // Sync permissions
        $this->syncPermissions();
        
        // Sync group permissions
        $groupPermissions = DB::connection('sso')->table('group_permissions')
            ->whereIn('group_id', $groups->pluck('id'))
            ->get();
        
        DB::connection('mysql')->table('group_permissions')
            ->whereIn('group_id', $groups->pluck('id'))
            ->delete();
        
        foreach ($groupPermissions as $gp) {
            DB::connection('mysql')->table('group_permissions')
                ->insert((array) $gp);
        }
    }
    
    /**
     * Sync permissions
     */
    public function syncPermissions()
    {
        $permissions = DB::connection('sso')->table('permissions')->get();
        
        foreach ($permissions as $permission) {
            $localPerm = DB::connection('mysql')->table('permissions')
                ->where('id', $permission->id)
                ->first();
            
            $permData = (array) $permission;
            
            if (!$localPerm) {
                DB::connection('mysql')->table('permissions')->insert($permData);
            } else {
                unset($permData['created_at']);
                DB::connection('mysql')->table('permissions')
                    ->where('id', $permission->id)
                    ->update($permData);
            }
        }
    }
    
    /**
     * Verify SSO token and sync user
     */
    public function verifySSOTokenAndSync($token)
    {
        // Verify JWT token
        try {
            $publicKey = file_get_contents(storage_path('keys/oauth-public.key'));
            $decoded = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($publicKey, 'RS256'));
            
            // Check if token is still valid
            if ($decoded->exp < time()) {
                return null;
            }
            
            // Sync user from SSO
            $this->syncUserToLocal($decoded->sub);
            
            // Get local user
            $user = User::on('mysql')->find($decoded->sub);
            
            return $user;
            
        } catch (\Exception $e) {
            Log::error('Token verification failed: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Create webhook for real-time sync
     */
    public function notifyDomainUpdate($event, $data)
    {
        $domains = DB::connection('sso')->table('domains')
            ->where('is_active', true)
            ->get();
        
        foreach ($domains as $domain) {
            if ($domain->webhook_url) {
                try {
                    $client = new \GuzzleHttp\Client();
                    $client->post($domain->webhook_url, [
                        'json' => [
                            'event' => $event,
                            'data' => $data,
                            'timestamp' => now()->toIso8601String(),
                            'signature' => $this->generateWebhookSignature($event, $data, $domain->client_secret),
                        ],
                        'timeout' => 5,
                    ]);
                } catch (\Exception $e) {
                    Log::warning("Webhook failed for domain {$domain->name}: " . $e->getMessage());
                }
            }
        }
    }
    
    /**
     * Generate webhook signature
     */
    private function generateWebhookSignature($event, $data, $secret)
    {
        $payload = json_encode(['event' => $event, 'data' => $data]);
        return hash_hmac('sha256', $payload, $secret);
    }
    
    /**
     * Sync user profile changes back to SSO
     */
    public function syncUserToSSO($localUserId, $changes)
    {
        $allowedFields = ['name', 'avatar_url', 'locale', 'timezone', 'preferences'];
        
        $updates = array_intersect_key($changes, array_flip($allowedFields));
        
        if (!empty($updates)) {
            DB::connection('sso')->table('users')
                ->where('id', $localUserId)
                ->update($updates);
            
            // Notify other domains
            $this->notifyDomainUpdate('user.updated', [
                'user_id' => $localUserId,
                'changes' => $updates,
            ]);
        }
    }
}