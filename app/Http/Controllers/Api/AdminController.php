<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Group;
use App\Models\Domain;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as HttpRequest;

class AdminController extends Controller
{
    /**
     * Get current user with permissions
     */
    public function me(): JsonResponse
    {
        $user = Auth::user();
        $user->load(['groups.permissions']);
        
        return response()->json([
            'user' => $user,
            'permissions' => $user->getAllPermissions(),
        ]);
    }

    /**
     * Get dashboard statistics
     */
    public function dashboard(): JsonResponse
    {
        $stats = [
            'users' => User::count(),
            'domains' => Domain::count(),
            'groups' => Group::count(),
            'permissions' => Permission::count(),
            'active_sessions' => DB::table('user_sessions')
                ->where('expires_at', '>', now())
                ->count(),
            'recent_logins' => User::orderBy('last_login_at', 'desc')
                ->take(10)
                ->get(['id', 'name', 'email_hash', 'last_login_at', 'last_login_ip']),
        ];

        return response()->json($stats);
    }

    /**
     * Get all users with pagination
     */
    public function users(Request $request): JsonResponse
    {
        $query = User::with(['groups']);

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email_hash', hash('sha256', strtolower($search)));
            });
        }

        if ($request->has('group_id')) {
            $groupId = $request->get('group_id');
            $query->whereHas('groups', function($q) use ($groupId) {
                $q->where('groups.id', $groupId);
            });
        }

        $users = $query->orderBy('created_at', 'desc')
                      ->paginate($request->get('per_page', 20));

        return response()->json($users);
    }

    /**
     * Get single user details
     */
    public function getUser(string $id): JsonResponse
    {
        $user = User::with(['groups.permissions'])->findOrFail($id);
        return response()->json($user);
    }

    /**
     * Create new user
     */
    public function createUser(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:8',
            'groups' => 'array',
            'groups.*' => 'exists:groups,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $emailHash = hash('sha256', strtolower($request->email));
        if (User::where('email_hash', $emailHash)->exists()) {
            return response()->json(['error' => 'Email already exists'], 422);
        }

        $user = new User();
        $user->id = (string) Str::uuid();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->setAttribute('email_hash', $emailHash);
        $user->password = Hash::make($request->password);
        $user->email_verified_at = $request->get('verify_email', false) ? now() : null;
        $user->save();

        if ($request->has('groups')) {
            foreach ($request->groups as $groupId) {
                DB::table('user_groups')->insert([
                    'user_id' => $user->id,
                    'group_id' => $groupId,
                    'assigned_at' => now(),
                    'assigned_by' => Auth::id(),
                    'is_primary' => false,
                ]);
            }
        }

        return response()->json($user, 201);
    }

    /**
     * Update user
     */
    public function updateUser(Request $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'email' => 'email|max:255',
            'password' => 'nullable|string|min:8',
            'groups' => 'array',
            'groups.*' => 'exists:groups,id',
            'mfa_enabled' => 'boolean',
            'locked_until' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->has('name')) {
            $user->name = $request->name;
        }

        if ($request->has('email') && $request->email !== $user->email) {
            $emailHash = hash('sha256', strtolower($request->email));
            if (User::where('email_hash', $emailHash)->where('id', '!=', $id)->exists()) {
                return response()->json(['error' => 'Email already exists'], 422);
            }
            $user->email = $request->email;
            $user->setAttribute('email_hash', $emailHash);
        }

        if ($request->has('password') && $request->password) {
            $user->password = Hash::make($request->password);
        }

        if ($request->has('mfa_enabled')) {
            $user->setAttribute('mfa_enabled', $request->mfa_enabled);
        }

        if ($request->has('locked_until')) {
            $user->setAttribute('locked_until', $request->locked_until);
            $user->setAttribute('failed_login_attempts', 0);
        }

        $user->save();

        if ($request->has('groups')) {
            DB::table('user_groups')->where('user_id', $user->id)->delete();
            foreach ($request->groups as $groupId) {
                DB::table('user_groups')->insert([
                    'user_id' => $user->id,
                    'group_id' => $groupId,
                    'assigned_at' => now(),
                    'assigned_by' => Auth::id(),
                    'is_primary' => false,
                ]);
            }
        }

        return response()->json($user);
    }

    /**
     * Delete user
     */
    public function deleteUser(string $id): JsonResponse
    {
        $user = User::findOrFail($id);
        
        // Don't allow deleting yourself
        if ($user->id === Auth::id()) {
            return response()->json(['error' => 'Cannot delete yourself'], 403);
        }

        $user->delete();
        return response()->json(['message' => 'User deleted successfully']);
    }

    /**
     * Get all groups with hierarchy
     */
    public function groups(Request $request): JsonResponse
    {
        $domainId = $request->get('domain_id');
        
        $groups = Group::with(['users', 'permissions'])
            ->when($domainId, function($query) use ($domainId) {
                return $query->where('domain_id', $domainId);
            })
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('sort_order')
            ->get();

        return response()->json($groups->map(function($group) {
            return $this->formatGroupTree($group);
        }));
    }

    /**
     * Format group for tree structure
     * @return array<string, mixed>
     */
    private function formatGroupTree(Group $group): array
    {
        return [
            'id' => $group->id,
            'name' => $group->name,
            'slug' => $group->slug,
            'description' => $group->description,
            'color' => $group->color,
            'icon' => $group->icon,
            'level' => $group->level,
            'path' => $group->path,
            'user_count' => $group->users()->count(),
            'max_users' => $group->max_users,
            'is_active' => $group->is_active,
            'permissions' => $group->permissions->pluck('slug'),
            'children' => $group->children->map(function($child) {
                return $this->formatGroupTree($child);
            }),
        ];
    }

    /**
     * Get single group details
     */
    public function getGroup(string $id): JsonResponse
    {
        $group = Group::with(['users', 'permissions', 'parent', 'children'])->findOrFail($id);
        return response()->json($group);
    }

    /**
     * Create new group
     */
    public function createGroup(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'domain_id' => 'required|exists:domains,id',
            'parent_id' => 'nullable|exists:groups,id',
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
            'description' => 'nullable|string',
            'color' => 'string|max:7',
            'icon' => 'nullable|string|max:50',
            'max_users' => 'nullable|integer|min:1',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $group = new Group();
        $group->fill($request->except('permissions'));
        $group->save();

        if ($request->has('permissions')) {
            foreach ($request->permissions as $permissionId) {
                DB::table('group_permissions')->insert([
                    'group_id' => $group->id,
                    'permission_id' => $permissionId,
                    'granted_by' => Auth::id(),
                    'can_delegate' => true,
                    'created_at' => now(),
                ]);
            }
        }

        return response()->json($group, 201);
    }

    /**
     * Update group
     */
    public function updateGroup(Request $request, string $id): JsonResponse
    {
        $group = Group::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'color' => 'string|max:7',
            'icon' => 'nullable|string|max:50',
            'max_users' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $group->fill($request->except(['permissions', 'domain_id', 'parent_id']));
        $group->save();

        if ($request->has('permissions')) {
            DB::table('group_permissions')->where('group_id', $group->id)->delete();
            foreach ($request->permissions as $permissionId) {
                DB::table('group_permissions')->insert([
                    'group_id' => $group->id,
                    'permission_id' => $permissionId,
                    'granted_by' => Auth::id(),
                    'can_delegate' => true,
                    'created_at' => now(),
                ]);
            }
        }

        return response()->json($group);
    }

    /**
     * Delete group
     */
    public function deleteGroup(string $id): JsonResponse
    {
        $group = Group::findOrFail($id);
        
        // Check if group has users
        if ($group->users()->count() > 0) {
            return response()->json(['error' => 'Cannot delete group with users'], 403);
        }

        $group->delete();
        return response()->json(['message' => 'Group deleted successfully']);
    }

    /**
     * Move group to new parent
     */
    public function moveGroup(Request $request, string $id): JsonResponse
    {
        $group = Group::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'parent_id' => 'nullable|exists:groups,id',
            'sort_order' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->has('parent_id')) {
            $parentGroup = $request->parent_id ? Group::find($request->parent_id) : null;
            $group->moveTo($parentGroup);
        }

        if ($request->has('sort_order')) {
            $group->reorder($request->sort_order);
        }

        return response()->json($group);
    }

    /**
     * Get all domains
     */
    public function domains(): JsonResponse
    {
        $domains = Domain::withCount(['groups', 'oauthClients'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($domains);
    }

    /**
     * Get all permissions
     */
    public function permissions(): JsonResponse
    {
        $permissions = Permission::orderBy('module')
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->groupBy('module');

        return response()->json($permissions);
    }
}