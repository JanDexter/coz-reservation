<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Admin extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'permission_level',
        'role_id',
        'permissions',
    ];

    protected $casts = [
        'permissions' => 'array',
    ];

    /**
     * Permission level constants
     */
    const LEVEL_SUPER_ADMIN = 'super_admin';
    const LEVEL_ADMIN = 'admin';
    const LEVEL_MODERATOR = 'moderator';

    /**
     * Get the user that owns this admin profile
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the role assigned to this admin
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get reservations managed by this admin
     */
    public function managedReservations()
    {
        return $this->hasMany(Reservation::class, 'created_by', 'user_id');
    }

    /**
     * Get refunds processed by this admin
     */
    public function processedRefunds()
    {
        return $this->hasMany(Refund::class, 'processed_by', 'user_id');
    }

    /**
     * Get transaction logs processed by this admin
     */
    public function processedTransactions()
    {
        return $this->hasMany(TransactionLog::class, 'processed_by', 'user_id');
    }

    /**
     * Check if admin has a specific permission
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->permission_level === self::LEVEL_SUPER_ADMIN) {
            return true; // Super admin has all permissions
        }

        // If admin has a role, check role permissions first
        if ($this->role) {
            if ($this->role->hasPermission($permission)) {
                return true;
            }
        }

        // Then check individual permissions
        return in_array($permission, $this->permissions ?? []);
    }

    /**
     * Check if admin is super admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->permission_level === self::LEVEL_SUPER_ADMIN;
    }

    /**
     * Get all permissions for this admin
     */
    public function getAllPermissions(): array
    {
        if ($this->isSuperAdmin()) {
            return ['*']; // Super admin has all permissions
        }

        $permissions = $this->permissions ?? [];
        
        if ($this->role) {
            $permissions = array_unique(array_merge($permissions, $this->role->permissions ?? []));
        }

        return array_values($permissions);
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
     * Grant permission to admin
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
     * Revoke permission from admin
     */
    public function revokePermission(string $permission): void
    {
        $permissions = $this->permissions ?? [];
        $this->permissions = array_values(array_filter($permissions, fn($p) => $p !== $permission));
        $this->save();
    }

    /**
     * Get formatted permission level
     */
    public function getFormattedPermissionLevelAttribute()
    {
        return match($this->permission_level) {
            self::LEVEL_SUPER_ADMIN => 'Super Administrator',
            self::LEVEL_ADMIN => 'Administrator',
            self::LEVEL_MODERATOR => 'Moderator',
            default => 'Unknown'
        };
    }
}
