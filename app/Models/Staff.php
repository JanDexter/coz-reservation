<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
    use HasFactory;

    protected $table = 'staff';

    protected $fillable = [
        'user_id',
        'position',
        'permissions',
        'role_id',
        'employee_id',
        'department',
        'hourly_rate',
        'hired_date',
    ];

    protected $casts = [
        'permissions' => 'array',
        'hourly_rate' => 'decimal:2',
        'hired_date' => 'date',
    ];

    /**
     * Get the user that owns this staff profile
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the role assigned to this staff
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Check if staff has a specific permission
     */
    public function hasPermission(string $permission): bool
    {
        // If staff has a role, check role permissions first
        if ($this->role) {
            if ($this->role->hasPermission($permission)) {
                return true;
            }
        }

        // Then check individual permissions
        return in_array($permission, $this->permissions ?? []);
    }

    /**
     * Get all permissions for this staff
     */
    public function getAllPermissions(): array
    {
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
     * Grant permission to staff
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
     * Revoke permission from staff
     */
    public function revokePermission(string $permission): void
    {
        $permissions = $this->permissions ?? [];
        $this->permissions = array_values(array_filter($permissions, fn($p) => $p !== $permission));
        $this->save();
    }

    /**
     * Get reservations processed by this staff member
     */
    public function processedReservations()
    {
        return $this->hasMany(Reservation::class, 'created_by', 'user_id');
    }

    /**
     * Get transaction logs processed by this staff member
     */
    public function processedTransactions()
    {
        return $this->hasMany(TransactionLog::class, 'processed_by', 'user_id');
    }

    /**
     * Get formatted hourly rate
     */
    public function getFormattedHourlyRateAttribute()
    {
        return $this->hourly_rate ? '₱' . number_format($this->hourly_rate, 2) : 'N/A';
    }
}
