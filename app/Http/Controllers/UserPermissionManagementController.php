<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Admin;
use App\Models\Staff;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserPermissionManagementController extends Controller
{
    /**
     * Show permission management page for a user
     */
    public function edit(User $user)
    {
        // Don't allow permission management for customers
        if ($user->isCustomer() && !$user->isAdmin() && !$user->isStaff()) {
            return redirect()->back()->with('error', 'Cannot manage permissions for customer accounts.');
        }

        $userData = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role_type' => $user->isAdmin() ? 'admin' : 'staff',
        ];

        $roleProfile = null;
        $currentPermissions = [];
        $currentRole = null;

        if ($user->isAdmin()) {
            $admin = $user->admin()->with('role')->first();
            if ($admin) {
                $roleProfile = $admin;
                $currentPermissions = $admin->getAllPermissions();
                $currentRole = $admin->role;
            }
        } elseif ($user->isStaff()) {
            $staff = $user->staff()->with('role')->first();
            if ($staff) {
                $roleProfile = $staff;
                $currentPermissions = $staff->getAllPermissions();
                $currentRole = $staff->role;
            }
        }

        $allPermissions = Permission::getAllPermissions();
        
        // Get available roles based on user type
        $availableRoles = Role::where('type', $userData['role_type'])
            ->orWhere('type', 'custom')
            ->orderBy('name')
            ->get()
            ->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                    'type' => $role->type,
                    'permissions' => $role->permissions ?? [],
                ];
            });

        return Inertia::render('UserPermissions/Edit', [
            'user' => $userData,
            'currentPermissions' => $currentPermissions,
            'currentRole' => $currentRole ? [
                'id' => $currentRole->id,
                'name' => $currentRole->name,
                'slug' => $currentRole->slug,
            ] : null,
            'allPermissions' => $allPermissions,
            'availableRoles' => $availableRoles,
        ]);
    }

    /**
     * Update user permissions
     */
    public function update(Request $request, User $user)
    {
        if ($user->isCustomer() && !$user->isAdmin() && !$user->isStaff()) {
            return back()->with('error', 'Cannot manage permissions for customer accounts.');
        }

        $validated = $request->validate([
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',
            'role_id' => 'nullable|exists:roles,id',
        ]);

        $roleProfile = null;

        if ($user->isAdmin()) {
            $roleProfile = $user->admin;
        } elseif ($user->isStaff()) {
            $roleProfile = $user->staff;
        }

        if (!$roleProfile) {
            return back()->with('error', 'User profile not found.');
        }

        // Update role
        if (isset($validated['role_id'])) {
            $roleProfile->role_id = $validated['role_id'];
        }

        // Update individual permissions
        $roleProfile->permissions = $validated['permissions'] ?? [];
        $roleProfile->save();

        return back()->with('success', 'Permissions updated successfully.');
    }

    /**
     * Toggle a specific permission for a user
     */
    public function togglePermission(Request $request, User $user)
    {
        if ($user->isCustomer() && !$user->isAdmin() && !$user->isStaff()) {
            return back()->with('error', 'Cannot manage permissions for customer accounts.');
        }

        $request->validate([
            'permission' => 'required|string',
        ]);

        $permission = $request->permission;
        $roleProfile = null;

        if ($user->isAdmin()) {
            $roleProfile = $user->admin;
        } elseif ($user->isStaff()) {
            $roleProfile = $user->staff;
        }

        if (!$roleProfile) {
            return back()->with('error', 'User profile not found.');
        }

        if ($roleProfile->hasPermission($permission)) {
            $roleProfile->revokePermission($permission);
            $message = 'Permission removed successfully.';
        } else {
            $roleProfile->grantPermission($permission);
            $message = 'Permission granted successfully.';
        }

        return back()->with('success', $message);
    }

    /**
     * Apply a preset of permissions to a user
     */
    public function applyPreset(Request $request, User $user)
    {
        if ($user->isCustomer() && !$user->isAdmin() && !$user->isStaff()) {
            return back()->with('error', 'Cannot manage permissions for customer accounts.');
        }

        $request->validate([
            'preset' => 'required|in:admin,staff',
        ]);

        $roleProfile = null;

        if ($user->isAdmin()) {
            $roleProfile = $user->admin;
        } elseif ($user->isStaff()) {
            $roleProfile = $user->staff;
        }

        if (!$roleProfile) {
            return back()->with('error', 'User profile not found.');
        }

        $roleProfile->setPresetPermissions($request->preset);

        return back()->with('success', 'Preset permissions applied successfully.');
    }

    /**
     * Assign a role to a user
     */
    public function assignRole(Request $request, User $user)
    {
        if ($user->isCustomer() && !$user->isAdmin() && !$user->isStaff()) {
            return back()->with('error', 'Cannot manage permissions for customer accounts.');
        }

        $request->validate([
            'role_id' => 'required|exists:roles,id',
        ]);

        $role = Role::findOrFail($request->role_id);
        $roleProfile = null;

        if ($user->isAdmin()) {
            $roleProfile = $user->admin;
        } elseif ($user->isStaff()) {
            $roleProfile = $user->staff;
        }

        if (!$roleProfile) {
            return back()->with('error', 'User profile not found.');
        }

        // Verify role type matches user type
        $userType = $user->isAdmin() ? 'admin' : 'staff';
        if ($role->type !== $userType && $role->type !== 'custom') {
            return back()->with('error', 'Invalid role type for this user.');
        }

        $roleProfile->role_id = $request->role_id;
        $roleProfile->save();

        return back()->with('success', 'Role assigned successfully.');
    }
}
