<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SSOSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create test domain
        $domainId = Str::uuid();
        DB::connection('sso')->table('domains')->insert([
            'id' => $domainId,
            'name' => 'todayislife',
            'display_name' => 'Today Is Life',
            'url' => 'https://todayislife.test',
            'client_id' => Str::uuid(),
            'client_secret' => Str::random(64),
            'is_active' => true,
            'allowed_origins' => json_encode(['https://todayislife.test']),
            'redirect_uris' => json_encode(['https://todayislife.test/auth/callback']),
            'logout_redirect_uri' => 'https://todayislife.test',
            'token_lifetime' => 3600,
            'refresh_token_lifetime' => 86400,
            'settings' => json_encode([
                'allow_registration' => true,
                'require_email_verification' => true,
                'allow_social_login' => true,
                'session_lifetime' => 120,
                'max_login_attempts' => 5,
                'lockout_duration' => 15,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create default groups
        $superadminGroupId = Str::uuid();
        $adminGroupId = Str::uuid();
        $userGroupId = Str::uuid();

        // Superadmin Group - System-wide
        DB::connection('sso')->table('groups')->insert([
            'id' => $superadminGroupId,
            'domain_id' => $domainId,
            'parent_id' => null,
            'name' => 'Superadmin',
            'slug' => 'superadmin',
            'description' => 'System-wide administrators with full access to all domains and features',
            'level' => 0,
            'path' => '/superadmin',
            'color' => '#e74c3c',
            'icon' => 'shield',
            'sort_order' => 0,
            'is_active' => true,
            'max_users' => null,
            'metadata' => json_encode(['is_system' => true]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Admin Group - Domain-specific
        DB::connection('sso')->table('groups')->insert([
            'id' => $adminGroupId,
            'domain_id' => $domainId,
            'parent_id' => null,
            'name' => 'Admin',
            'slug' => 'admin',
            'description' => 'Domain administrators with full access to their website',
            'level' => 0,
            'path' => '/admin',
            'color' => '#f39c12',
            'icon' => 'cog',
            'sort_order' => 1,
            'is_active' => true,
            'max_users' => null,
            'metadata' => json_encode(['is_system' => true]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // User Group - Basic users
        DB::connection('sso')->table('groups')->insert([
            'id' => $userGroupId,
            'domain_id' => $domainId,
            'parent_id' => null,
            'name' => 'User',
            'slug' => 'user',
            'description' => 'Regular users with read-only access to public pages',
            'level' => 0,
            'path' => '/user',
            'color' => '#3498db',
            'icon' => 'user',
            'sort_order' => 2,
            'is_active' => true,
            'max_users' => null,
            'metadata' => json_encode(['is_system' => true]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create permissions
        $permissions = [
            // System permissions for Superadmin
            ['id' => Str::uuid(), 'name' => 'System Management', 'slug' => 'system.manage', 'description' => 'Full system access', 'module' => 'system', 'category' => 'administration', 'is_system' => true],
            ['id' => Str::uuid(), 'name' => 'Domain Management', 'slug' => 'domains.manage', 'description' => 'Manage all domains', 'module' => 'domains', 'category' => 'administration', 'is_system' => true],
            ['id' => Str::uuid(), 'name' => 'User Management (Global)', 'slug' => 'users.manage.global', 'description' => 'Manage users across all domains', 'module' => 'users', 'category' => 'administration', 'is_system' => true],
            ['id' => Str::uuid(), 'name' => 'Group Management (Global)', 'slug' => 'groups.manage.global', 'description' => 'Manage groups across all domains', 'module' => 'groups', 'category' => 'administration', 'is_system' => true],
            
            // Admin permissions for domain-specific management
            ['id' => Str::uuid(), 'name' => 'User Management', 'slug' => 'users.manage', 'description' => 'Manage users in own domain', 'module' => 'users', 'category' => 'management', 'is_system' => false],
            ['id' => Str::uuid(), 'name' => 'Group Management', 'slug' => 'groups.manage', 'description' => 'Manage groups in own domain', 'module' => 'groups', 'category' => 'management', 'is_system' => false],
            ['id' => Str::uuid(), 'name' => 'Content Management', 'slug' => 'content.manage', 'description' => 'Manage website content', 'module' => 'content', 'category' => 'management', 'is_system' => false],
            ['id' => Str::uuid(), 'name' => 'Settings Management', 'slug' => 'settings.manage', 'description' => 'Manage domain settings', 'module' => 'settings', 'category' => 'management', 'is_system' => false],
            
            // User permissions for basic access
            ['id' => Str::uuid(), 'name' => 'View Pages', 'slug' => 'pages.view', 'description' => 'View public pages', 'module' => 'pages', 'category' => 'access', 'is_system' => false],
            ['id' => Str::uuid(), 'name' => 'Profile Management', 'slug' => 'profile.manage', 'description' => 'Manage own profile', 'module' => 'profile', 'category' => 'user', 'is_system' => false],
        ];

        foreach ($permissions as $permission) {
            $permission['metadata'] = json_encode(['scope' => $permission['is_system'] ? 'global' : 'domain']);
            $permission['created_at'] = now();
            $permission['updated_at'] = now();
            DB::connection('sso')->table('permissions')->insert($permission);
        }

        // Get permission IDs
        $systemManageId = DB::connection('sso')->table('permissions')->where('slug', 'system.manage')->value('id');
        $domainsManageId = DB::connection('sso')->table('permissions')->where('slug', 'domains.manage')->value('id');
        $usersManageGlobalId = DB::connection('sso')->table('permissions')->where('slug', 'users.manage.global')->value('id');
        $groupsManageGlobalId = DB::connection('sso')->table('permissions')->where('slug', 'groups.manage.global')->value('id');
        $usersManageId = DB::connection('sso')->table('permissions')->where('slug', 'users.manage')->value('id');
        $groupsManageId = DB::connection('sso')->table('permissions')->where('slug', 'groups.manage')->value('id');
        $contentManageId = DB::connection('sso')->table('permissions')->where('slug', 'content.manage')->value('id');
        $settingsManageId = DB::connection('sso')->table('permissions')->where('slug', 'settings.manage')->value('id');
        $pagesViewId = DB::connection('sso')->table('permissions')->where('slug', 'pages.view')->value('id');
        $profileManageId = DB::connection('sso')->table('permissions')->where('slug', 'profile.manage')->value('id');

        // Assign permissions to groups
        // Superadmin gets all permissions
        $allPermissions = [
            $systemManageId, $domainsManageId, $usersManageGlobalId, $groupsManageGlobalId,
            $usersManageId, $groupsManageId, $contentManageId, $settingsManageId,
            $pagesViewId, $profileManageId
        ];
        
        foreach ($allPermissions as $permissionId) {
            DB::connection('sso')->table('group_permissions')->insert([
                'group_id' => $superadminGroupId,
                'permission_id' => $permissionId,
                'granted_by' => null,
                'can_delegate' => true,
                'created_at' => now(),
            ]);
        }

        // Admin gets domain-specific permissions
        $adminPermissions = [
            $usersManageId, $groupsManageId, $contentManageId, $settingsManageId,
            $pagesViewId, $profileManageId
        ];
        
        foreach ($adminPermissions as $permissionId) {
            DB::connection('sso')->table('group_permissions')->insert([
                'group_id' => $adminGroupId,
                'permission_id' => $permissionId,
                'granted_by' => null,
                'can_delegate' => true,
                'created_at' => now(),
            ]);
        }

        // User gets basic permissions
        $userPermissions = [$pagesViewId, $profileManageId];
        
        foreach ($userPermissions as $permissionId) {
            DB::connection('sso')->table('group_permissions')->insert([
                'group_id' => $userGroupId,
                'permission_id' => $permissionId,
                'granted_by' => null,
                'can_delegate' => false,
                'created_at' => now(),
            ]);
        }

        // Create test users
        $superadminUserId = Str::uuid();
        $adminUserId = Str::uuid();
        $userId = Str::uuid();

        // Superadmin user
        DB::connection('sso')->table('users')->insert([
            'id' => $superadminUserId,
            'name' => 'Super Admin',
            'email' => 'superadmin@todayislife.test', // Will be encrypted by model
            'email_hash' => hash('sha256', strtolower('superadmin@todayislife.test')),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'phone' => null,
            'phone_hash' => null,
            'phone_verified_at' => null,
            'mfa_secret' => null,
            'mfa_enabled' => false,
            'mfa_recovery_codes' => null,
            'avatar_url' => null,
            'locale' => 'de',
            'timezone' => 'Europe/Berlin',
            'preferences' => json_encode([]),
            'last_login_at' => null,
            'last_login_ip' => null,
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'remember_token' => null,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);

        // Admin user
        DB::connection('sso')->table('users')->insert([
            'id' => $adminUserId,
            'name' => 'Admin User',
            'email' => 'admin@todayislife.test', // Will be encrypted by model
            'email_hash' => hash('sha256', strtolower('admin@todayislife.test')),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'phone' => null,
            'phone_hash' => null,
            'phone_verified_at' => null,
            'mfa_secret' => null,
            'mfa_enabled' => false,
            'mfa_recovery_codes' => null,
            'avatar_url' => null,
            'locale' => 'de',
            'timezone' => 'Europe/Berlin',
            'preferences' => json_encode([]),
            'last_login_at' => null,
            'last_login_ip' => null,
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'remember_token' => null,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);

        // Regular user
        DB::connection('sso')->table('users')->insert([
            'id' => $userId,
            'name' => 'Test User',
            'email' => 'user@todayislife.test', // Will be encrypted by model
            'email_hash' => hash('sha256', strtolower('user@todayislife.test')),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'phone' => null,
            'phone_hash' => null,
            'phone_verified_at' => null,
            'mfa_secret' => null,
            'mfa_enabled' => false,
            'mfa_recovery_codes' => null,
            'avatar_url' => null,
            'locale' => 'de',
            'timezone' => 'Europe/Berlin',
            'preferences' => json_encode([]),
            'last_login_at' => null,
            'last_login_ip' => null,
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'remember_token' => null,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);

        // Assign users to groups
        DB::connection('sso')->table('user_groups')->insert([
            'user_id' => $superadminUserId,
            'group_id' => $superadminGroupId,
            'assigned_at' => now(),
            'assigned_by' => null,
            'expires_at' => null,
            'is_primary' => true,
        ]);

        DB::connection('sso')->table('user_groups')->insert([
            'user_id' => $adminUserId,
            'group_id' => $adminGroupId,
            'assigned_at' => now(),
            'assigned_by' => $superadminUserId,
            'expires_at' => null,
            'is_primary' => true,
        ]);

        DB::connection('sso')->table('user_groups')->insert([
            'user_id' => $userId,
            'group_id' => $userGroupId,
            'assigned_at' => now(),
            'assigned_by' => $adminUserId,
            'expires_at' => null,
            'is_primary' => true,
        ]);

        // Create OAuth client for the domain
        DB::connection('sso')->table('oauth_clients')->insert([
            'id' => Str::uuid(),
            'user_id' => null, // System client, not user-specific
            'name' => 'Today Is Life Web App',
            'secret' => Str::random(64),
            'provider' => null,
            'redirect_uris' => json_encode(['https://todayislife.test/auth/callback']),
            'allowed_scopes' => json_encode(['openid', 'profile', 'email']),
            'allowed_grants' => json_encode(['authorization_code', 'refresh_token']),
            'personal_access_client' => false,
            'password_client' => false,
            'trusted' => true,
            'revoked' => false,
            'access_token_ttl' => 3600,
            'refresh_token_ttl' => 86400,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('SSO database seeded successfully!');
        $this->command->info('Test users created:');
        $this->command->info('  - Superadmin: superadmin@todayislife.test (password: password)');
        $this->command->info('  - Admin: admin@todayislife.test (password: password)');
        $this->command->info('  - User: user@todayislife.test (password: password)');
    }
}
