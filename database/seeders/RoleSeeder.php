<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all available permissions as a flat array
        $allPermissions = Permission::getAllPermissionKeys();
        
        // Create Administrator role with all permissions
        Role::updateOrCreate(
            ['slug' => 'administrator'],
            [
                'name' => 'Administrator',
                'description' => 'Full system access with all permissions',
                'type' => 'admin',
                'is_system_role' => true,
                'permissions' => $allPermissions
            ]
        );
        
        // Create Staff role with limited permissions
        $staffPermissions = collect($allPermissions)->filter(function ($permission) {
            // Staff gets all permissions except user management and critical settings
            return !str_starts_with($permission, 'user_management') 
                && !str_starts_with($permission, 'settings.manage_system_settings');
        })->values()->all();
        
        Role::updateOrCreate(
            ['slug' => 'staff-member'],
            [
                'name' => 'Staff Member',
                'description' => 'Standard staff access for daily operations',
                'type' => 'staff',
                'is_system_role' => true,
                'permissions' => $staffPermissions
            ]
        );
        
        // Create Read-Only Staff role
        $readOnlyPermissions = collect($allPermissions)->filter(function ($permission) {
            // Only view permissions
            return str_contains($permission, 'view_');
        })->values()->all();
        
        Role::updateOrCreate(
            ['slug' => 'staff-readonly'],
            [
                'name' => 'Staff (Read-Only)',
                'description' => 'View-only access for staff members',
                'type' => 'staff',
                'is_system_role' => false,
                'permissions' => $readOnlyPermissions
            ]
        );
        
        $this->command->info('System roles created successfully!');
    }
}
