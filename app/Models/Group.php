<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Collection;

/**
 * @property string $id
 * @property string $domain_id
 * @property string|null $parent_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $level
 * @property string $path
 * @property string|null $color
 * @property string|null $icon
 * @property int $sort_order
 * @property bool $is_active
 * @property int|null $max_users
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Group extends Model
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
    /**
     * @var array<string>
     */
    protected $fillable = [
        'domain_id',
        'parent_id',
        'name',
        'slug',
        'description',
        'level',
        'path',
        'color',
        'icon',
        'sort_order',
        'is_active',
        'max_users',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
        'level' => 'integer',
        'sort_order' => 'integer',
        'max_users' => 'integer',
    ];

    /**
     * Boot the model
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($group) {
            if (!$group->slug) {
                $group->slug = Str::slug($group->name);
            }
            
            if ($group->parent_id) {
                $parent = static::find($group->parent_id);
                $group->level = $parent->level + 1;
                $group->path = $parent->path . '/' . $group->slug;
                $group->domain_id = $parent->domain_id;
            } else {
                $group->level = 0;
                $group->path = '/' . $group->slug;
            }
        });

        static::updating(function ($group) {
            if ($group->isDirty('parent_id')) {
                $group->updateHierarchy();
            }
        });

        static::deleting(function ($group) {
            // Move children to parent or make them root
            $group->children()->update(['parent_id' => $group->parent_id]);
        });
    }

    /**
     * Update hierarchy when parent changes
     */
    public function updateHierarchy(): void
    {
        if ($this->parent_id) {
            $parent = static::find($this->parent_id);
            $this->level = $parent->level + 1;
            $this->path = $parent->path . '/' . $this->slug;
        } else {
            $this->level = 0;
            $this->path = '/' . $this->slug;
        }

        // Update all descendants
        $this->updateDescendants();
    }

    /**
     * Update all descendant groups
     */
    public function updateDescendants(): void
    {
        foreach ($this->children as $child) {
            $child->level = $this->level + 1;
            $child->path = $this->path . '/' . $child->slug;
            $child->save();
            $child->updateDescendants();
        }
    }

    /**
     * Domain relationship
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * Parent group relationship
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'parent_id');
    }

    /**
     * Children groups relationship
     */
    public function children(): HasMany
    {
        return $this->hasMany(Group::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * Get all descendants
     */
    public function descendants(): Collection
    {
        $descendants = collect();
        
        foreach ($this->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->descendants());
        }
        
        return $descendants;
    }

    /**
     * Get all ancestors
     */
    public function ancestors(): Collection
    {
        $ancestors = collect();
        $parent = $this->parent;
        
        while ($parent) {
            $ancestors->push($parent);
            $parent = $parent->parent;
        }
        
        return $ancestors;
    }

    /**
     * Get root group
     */
    public function root(): Group
    {
        $root = $this;
        
        while ($root->parent) {
            $root = $root->parent;
        }
        
        return $root;
    }

    /**
     * Check if group is root
     */
    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * Check if group is leaf (no children)
     */
    public function isLeaf(): bool
    {
        return $this->children()->count() === 0;
    }

    /**
     * Check if group is ancestor of another group
     */
    public function isAncestorOf(Group $group): bool
    {
        return $group->ancestors()->contains('id', $this->id);
    }

    /**
     * Check if group is descendant of another group
     */
    public function isDescendantOf(Group $group): bool
    {
        return $this->ancestors()->contains('id', $group->id);
    }

    /**
     * Users relationship
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_groups')
            ->withPivot(['assigned_at', 'assigned_by', 'expires_at', 'is_primary'])
            ->wherePivot('expires_at', '>', now())->orWhereNull('user_groups.expires_at');
    }

    /**
     * Get active users count
     */
    public function getActiveUsersCountAttribute(): int
    {
        return $this->users()->count();
    }

    /**
     * Check if group has space for more users
     */
    public function hasSpace(): bool
    {
        if (!$this->max_users) {
            return true;
        }
        
        return $this->active_users_count < $this->max_users;
    }

    /**
     * Permissions relationship
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'group_permissions')
            ->withPivot(['granted_by', 'can_delegate', 'created_at']);
    }

    /**
     * Get all permissions including inherited from parents
     */
    public function getAllPermissions(): Collection
    {
        $permissions = $this->permissions;
        
        // Inherit permissions from parent groups
        if ($this->parent) {
            $permissions = $permissions->merge($this->parent->getAllPermissions());
        }
        
        return $permissions->unique('id');
    }

    /**
     * Check if group has specific permission
     */
    public function hasPermission(string|Permission $permission): bool
    {
        if (is_string($permission)) {
            return $this->getAllPermissions()->contains('slug', $permission);
        }
        
        return $this->getAllPermissions()->contains('id', $permission->id);
    }

    /**
     * Grant permission to group
     */
    public function grantPermission(string|Permission $permission, ?string $grantedBy = null, bool $canDelegate = false): bool
    {
        $permissionModel = is_string($permission) 
            ? Permission::where('slug', $permission)->first() 
            : $permission;
        
        if (!$permissionModel) {
            return false;
        }
        
        $this->permissions()->attach($permissionModel->id, [
            'granted_by' => $grantedBy,
            'can_delegate' => $canDelegate,
            'created_at' => now(),
        ]);
        
        return true;
    }

    /**
     * Revoke permission from group
     */
    public function revokePermission(string|Permission $permission): bool
    {
        $permissionModel = is_string($permission) 
            ? Permission::where('slug', $permission)->first() 
            : $permission;
        
        if (!$permissionModel) {
            return false;
        }
        
        $this->permissions()->detach($permissionModel->id);
        
        return true;
    }

    /**
     * Add user to group
     */
    public function addUser(User $user, ?string $assignedBy = null, bool $isPrimary = false, ?\Carbon\Carbon $expiresAt = null): bool
    {
        if (!$this->hasSpace()) {
            throw new \Exception('Group has reached maximum user limit');
        }
        
        $this->users()->attach($user->id, [
            'assigned_at' => now(),
            'assigned_by' => $assignedBy,
            'is_primary' => $isPrimary,
            'expires_at' => $expiresAt,
        ]);
        
        return true;
    }

    /**
     * Remove user from group
     */
    public function removeUser(User $user): bool
    {
        $this->users()->detach($user->id);
        return true;
    }

    /**
     * Get group hierarchy as tree
     */
    public static function getTree(string $domainId): Collection
    {
        $groups = static::where('domain_id', $domainId)
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('sort_order')
            ->get();
        
        return $groups->map(function ($group) {
            return $group->toTree();
        });
    }

    /**
     * Convert group to tree structure
     */
    public function toTree(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'level' => $this->level,
            'path' => $this->path,
            'color' => $this->color,
            'icon' => $this->icon,
            'is_active' => $this->is_active,
            'users_count' => $this->active_users_count,
            'max_users' => $this->max_users,
            'children' => $this->children->map(function ($child) {
                return $child->toTree();
            }),
        ];
    }

    /**
     * Move group to new parent
     */
    public function moveTo(?Group $newParent = null): Group
    {
        $this->parent_id = $newParent ? $newParent->id : null;
        $this->updateHierarchy();
        $this->save();
        
        return $this;
    }

    /**
     * Reorder group
     */
    public function reorder(int $position): Group
    {
        $this->sort_order = $position;
        $this->save();
        
        // Reorder siblings
        $siblings = static::where('parent_id', $this->parent_id)
            ->where('domain_id', $this->domain_id)
            ->where('id', '!=', $this->id)
            ->orderBy('sort_order')
            ->get();
        
        $order = 0;
        foreach ($siblings as $sibling) {
            if ($order == $position) {
                $order++;
            }
            $sibling->sort_order = $order;
            $sibling->save();
            $order++;
        }
        
        return $this;
    }
}
