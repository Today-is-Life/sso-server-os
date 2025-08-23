<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Permission extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'module',
        'category',
        'is_system',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_system' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Boot the model
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Permission $permission): void {
            if (!$permission->slug) {
                $permission->slug = Str::slug($permission->name);
            }
        });
    }

    /**
     * Groups relationship
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_permissions')
            ->withPivot(['granted_by', 'can_delegate', 'created_at']);
    }

    /**
     * Get all permissions by module
     */
    public static function getByModule(string $module): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('module', $module)->orderBy('name')->get();
    }

    /**
     * Get all permissions by category
     */
    public static function getByCategory(string $category): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('category', $category)->orderBy('name')->get();
    }

    /**
     * Find permission by slug
     */
    public static function findBySlug(string $slug): ?Permission
    {
        return static::where('slug', $slug)->first();
    }

    /**
     * Check if permission is system permission
     */
    public function isSystem(): bool
    {
        return $this->is_system;
    }

    /**
     * Get permission display name
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name . ($this->category ? " ({$this->category})" : '');
    }
}
