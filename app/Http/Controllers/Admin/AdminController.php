<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Group;
use App\Models\Domain;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use App\Services\DomainPermissionSyncService;

class AdminController extends Controller
{
    /**
     * Dashboard
     */
    public function dashboard(): View
    {
        $stats = [
            'users' => User::count(),
            'groups' => Group::count(),
            'domains' => Domain::count(),
            'permissions' => Permission::count(),
            'active_sessions' => DB::table('user_sessions')
                ->where('expires_at', '>', now())
                ->whereNull('revoked_at')
                ->count(),
        ];

        $recentUsers = User::latest()->take(5)->get();
        $recentLogins = DB::table('audit_logs')
            ->where('action', 'login')
            ->latest()
            ->take(10)
            ->get();

        if (request()->header('HX-Request')) {
            return view('admin.partials.dashboard', compact('stats', 'recentUsers', 'recentLogins'));
        }

        return view('spa');
    }

    /**
     * Users Management
     */
    public function users(Request $request): View
    {
        $query = User::with(['groups']);
        
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email_hash', hash('sha256', strtolower($search)));
            });
        }

        $users = $query->paginate(20);

        if (request()->header('HX-Request')) {
            return view('admin.partials.users', compact('users'));
        }

        return view('spa');
    }

    /**
     * Create User Form
     */
    public function createUser(): View
    {
        if (request()->header('HX-Request')) {
            return view('admin.partials.user-create');
        }

        return view('spa');
    }

    /**
     * Edit User
     */
    public function editUser(string $id): View
    {
        $user = User::with('groups')->findOrFail($id);
        $groups = Group::where('is_active', true)->get();
        $userGroupIds = $user->groups->pluck('id')->toArray();

        if (request()->header('HX-Request')) {
            return view('admin.partials.user-edit', compact('user', 'groups', 'userGroupIds'));
        }

        return view('spa');
    }

    /**
     * Groups Management
     */
    public function groups(): View
    {
        $isSuperadmin = Auth::user()->hasPermission('system.manage');
        
        if ($isSuperadmin) {
            // Superadmin sees all groups across all domains
            $groups = Group::with(['permissions', 'children'])
                ->whereNull('parent_id')
                ->orderBy('sort_order')
                ->get();
        } else {
            // Admin sees only groups for their domain
            $domainId = session('current_domain_id');
            $groups = Group::with(['permissions', 'children'])
                ->where('domain_id', $domainId)
                ->whereNull('parent_id')
                ->orderBy('sort_order')
                ->get();
        }

        if (request()->header('HX-Request')) {
            return view('admin.partials.groups', compact('groups', 'isSuperadmin'));
        }

        return view('spa');
    }

    /**
     * Domains Management (Superadmin only)
     */
    public function domains(): View
    {
        if (!Auth::user()->hasPermission('domains.manage')) {
            abort(403);
        }

        $domains = Domain::withCount('groups')->get();

        if (request()->header('HX-Request')) {
            return view('admin.partials.domains', compact('domains'));
        }

        return view('spa');
    }

    /**
     * Create Domain Form
     */
    public function createDomain(): View
    {
        if (!Auth::user()->hasPermission('domains.manage')) {
            abort(403);
        }

        if (request()->header('HX-Request')) {
            return view('admin.partials.domain-create');
        }

        return view('spa');
    }

    /**
     * Edit Domain
     */
    public function editDomain(string $id): View
    {
        if (!Auth::user()->hasPermission('domains.manage')) {
            abort(403);
        }

        $domain = Domain::findOrFail($id);

        if (request()->header('HX-Request')) {
            return view('admin.partials.domain-edit', compact('domain'));
        }

        return view('spa');
    }

    /**
     * Store Domain
     */
    public function storeDomain(Request $request): JsonResponse
    {
        if (!Auth::user()->hasPermission('domains.manage')) {
            return response()->json(['error' => 'Keine Berechtigung'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:domains,name',
            'display_name' => 'required|string|max:100',
            'url' => 'required|url|unique:domains,url',
            'allowed_origins' => 'nullable|string',
            'redirect_uris' => 'nullable|string',
            'logout_redirect_uri' => 'required|url',
        ]);

        $domain = new Domain();
        $domain->name = $validated['name'];
        $domain->display_name = $validated['display_name'];
        $domain->url = $validated['url'];
        $domain->client_id = \Str::uuid();
        $domain->client_secret = \Str::random(64);
        $domain->is_active = true;
        $domain->allowed_origins = array_filter(explode("\n", $validated['allowed_origins'] ?? ''));
        $domain->redirect_uris = array_filter(explode("\n", $validated['redirect_uris'] ?? ''));
        $domain->logout_redirect_uri = $validated['logout_redirect_uri'];
        $domain->token_lifetime = 3600;
        $domain->refresh_token_lifetime = 86400;
        $domain->settings = [
            'allow_registration' => true,
            'require_email_verification' => true,
            'allow_social_login' => false,
            'session_lifetime' => 120,
            'max_login_attempts' => 5,
            'lockout_duration' => 15,
        ];
        $domain->save();

        return response()->json(['success' => true, 'message' => 'Domain erstellt']);
    }

    /**
     * Update Domain
     */
    public function updateDomain(Request $request, string $id): JsonResponse
    {
        if (!Auth::user()->hasPermission('domains.manage')) {
            return response()->json(['error' => 'Keine Berechtigung'], 403);
        }

        $domain = Domain::findOrFail($id);
        
        $validated = $request->validate([
            'display_name' => 'required|string|max:100',
            'url' => 'required|url|unique:domains,url,' . $domain->id,
            'is_active' => 'boolean',
            'allowed_origins' => 'nullable|string',
            'redirect_uris' => 'nullable|string',
            'logout_redirect_uri' => 'required|url',
        ]);

        $domain->display_name = $validated['display_name'];
        $domain->url = $validated['url'];
        $domain->is_active = $validated['is_active'] ?? true;
        $domain->allowed_origins = array_filter(explode("\n", $validated['allowed_origins'] ?? ''));
        $domain->redirect_uris = array_filter(explode("\n", $validated['redirect_uris'] ?? ''));
        $domain->logout_redirect_uri = $validated['logout_redirect_uri'];
        $domain->save();

        return response()->json(['success' => true, 'message' => 'Domain aktualisiert']);
    }

    /**
     * Delete Domain
     */
    public function deleteDomain(string $id): JsonResponse
    {
        if (!Auth::user()->hasPermission('domains.manage')) {
            return response()->json(['error' => 'Keine Berechtigung'], 403);
        }

        $domain = Domain::findOrFail($id);
        $domain->delete();
        
        return response()->json(['success' => true, 'message' => 'Domain gelöscht']);
    }

    /**
     * Permissions Management
     */
    public function permissions(): View
    {
        $permissions = Permission::orderBy('category')->orderBy('name')->get();
        $groups = Group::all();

        if (request()->header('HX-Request')) {
            return view('admin.partials.permissions', compact('permissions', 'groups'));
        }

        return view('spa');
    }

    /**
     * Store new user
     */
    public function storeUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email_hash',
            'password' => 'required|min:8',
            'group_ids' => 'array',
            'group_ids.*' => 'exists:groups,id',
        ]);

        $user = new User();
        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->password = bcrypt($validated['password']);
        $user->email_verified_at = now();
        $user->save();

        if (!empty($validated['group_ids'])) {
            foreach ($validated['group_ids'] as $groupId) {
                DB::table('user_groups')->insert([
                    'user_id' => $user->id,
                    'group_id' => $groupId,
                    'assigned_at' => now(),
                    'assigned_by' => Auth::id(),
                    'is_primary' => false,
                ]);
            }
        }

        return response()->json(['success' => true, 'message' => 'Benutzer erstellt']);
    }

    /**
     * Update user
     */
    public function updateUser(Request $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'group_ids' => 'array',
            'group_ids.*' => 'exists:groups,id',
        ]);

        $user->name = $validated['name'];
        $currentEmail = $user->getEmailAttribute($user->getAttributes()['email']);
        if ($currentEmail !== $validated['email']) {
            $user->email = $validated['email'];
        }
        $user->save();

        // Update groups
        DB::table('user_groups')->where('user_id', $user->id)->delete();
        if (!empty($validated['group_ids'])) {
            foreach ($validated['group_ids'] as $groupId) {
                DB::table('user_groups')->insert([
                    'user_id' => $user->id,
                    'group_id' => $groupId,
                    'assigned_at' => now(),
                    'assigned_by' => Auth::id(),
                    'is_primary' => false,
                ]);
            }
        }

        return response()->json(['success' => true, 'message' => 'Benutzer aktualisiert']);
    }

    /**
     * Delete user
     */
    public function deleteUser(string $id): JsonResponse
    {
        $user = User::findOrFail($id);
        
        // Don't allow deleting yourself
        if ($user->id === Auth::id()) {
            return response()->json(['error' => 'Sie können sich nicht selbst löschen'], 400);
        }
        
        $user->delete();
        
        return response()->json(['success' => true, 'message' => 'Benutzer gelöscht']);
    }

    /**
     * Sync permissions from a domain
     */
    public function syncDomainPermissions(Request $request, DomainPermissionSyncService $syncService): JsonResponse
    {
        if (!Auth::user()->hasPermission('domains.manage')) {
            return response()->json(['error' => 'Keine Berechtigung'], 403);
        }

        $validated = $request->validate([
            'domain_id' => 'required|exists:domains,id',
        ]);

        $domain = Domain::findOrFail($validated['domain_id']);
        /** @var \App\Models\Domain $domain */
        $result = $syncService->syncDomainPermissions($domain);

        if ($result['success']) {
            return response()->json($result);
        }

        return response()->json(['error' => $result['error']], 500);
    }

    /**
     * Sync permissions from all domains
     */
    public function syncAllPermissions(DomainPermissionSyncService $syncService): JsonResponse
    {
        if (!Auth::user()->hasPermission('system.manage')) {
            return response()->json(['error' => 'Keine Berechtigung'], 403);
        }

        $results = $syncService->syncAllDomains();
        
        return response()->json([
            'success' => true,
            'results' => $results,
        ]);
    }
}