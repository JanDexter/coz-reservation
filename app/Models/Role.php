<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'permissions',
        'type',
        'is_system_role',
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_system_role' => 'boolean',
    ];

    /**
     * Role type constants
     */
    const TYPE_ADMIN = 'admin';
    const TYPE_STAFF = 'staff';
    const TYPE_CUSTOM = 'custom';

    /**
     * Boot method to auto-generate slug
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($role) {
            if (empty($role->slug)) {
                $role->slug = Str::slug($role->name);
            }
        });

        static::updating(function ($role) {
            if ($role->isDirty('name') && empty($role->slug)) {
                $role->slug = Str::slug($role->name);
            }
        });
    }

    /**
     * Get admins with this role
     */
    public function admins()
    {
        return $this->hasMany(Admin::class);
    }

    /**
     * Get staff with this role
     */
    public function staff()
    {
        return $this->hasMany(Staff::class);
    }

    /**
     * Check if role has a specific permission
     */
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? []);
    }

    /**
     * Grant permission to role
     */
    public function grantPermission(string $permission): void
    {
        $permissions = $this->permissions ?? [];
        if (!in_array($permission, $permissions)) {
            $permissions[] = $permission;
            $this->permissions = $permissions;
            $this->save();
        }
    }

    /**
     * Revoke permission from role
     */
    public function revokePermission(string $permission): void
    {
        $permissions = $this->permissions ?? [];
        $this->permissions = array_values(array_filter($permissions, fn($p) => $p !== $permission));
        $this->save();
    }

    /**
     * Set permissions from preset
     */
    public function setPresetPermissions(string $roleType): void
    {
        $this->permissions = Permission::getPresetPermissions($roleType);
        $this->save();
    }

    /**
     * Get count of users with this role
     */
    public function getUsersCountAttribute(): int
    {
        return $this->admins()->count() + $this->staff()->count();
    }

    /**
     * Check if role can be deleted
     */
    public function canBeDeleted(): bool
    {
        return !$this->is_system_role && $this->getUsersCountAttribute() === 0;
    }
}
