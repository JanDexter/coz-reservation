<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    /**
     * Display a listing of roles
     */
    public function index()
    {
        $roles = Role::withCount(['admins', 'staff'])
            ->orderBy('is_system_role', 'desc')
            ->orderBy('name')
            ->get()
            ->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                    'description' => $role->description,
                    'type' => $role->type,
                    'is_system_role' => $role->is_system_role,
                    'permissions' => $role->permissions ?? [],
                    'users_count' => $role->admins_count + $role->staff_count,
                    'can_be_deleted' => !$role->is_system_role && ($role->admins_count + $role->staff_count) === 0,
                ];
            });

        $allPermissions = Permission::getAllPermissions();

        return Inertia::render('Roles/Index', [
            'roles' => $roles,
            'allPermissions' => $allPermissions,
        ]);
    }

    /**
     * Show the form for creating a new role
     */
    public function create()
    {
        $allPermissions = Permission::getAllPermissions();

        return Inertia::render('Roles/Create', [
            'allPermissions' => $allPermissions,
        ]);
    }

    /**
     * Store a newly created role
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'description' => 'nullable|string|max:500',
            'type' => 'required|in:admin,staff,custom',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'],
            'permissions' => $validated['permissions'] ?? [],
            'is_system_role' => false,
        ]);

        return redirect()->route('roles.index')->with('success', 'Role created successfully.');
    }

    /**
     * Display the specified role
     */
    public function show(Role $role)
    {
        $role->load(['admins.user', 'staff.user']);

        $users = collect()
            ->merge($role->admins->map(function ($admin) {
                return [
                    'id' => $admin->user->id,
                    'name' => $admin->user->name,
                    'email' => $admin->user->email,
                    'type' => 'admin',
                ];
            }))
            ->merge($role->staff->map(function ($staff) {
                return [
                    'id' => $staff->user->id,
                    'name' => $staff->user->name,
                    'email' => $staff->user->email,
                    'type' => 'staff',
                ];
            }))
            ->values();

        $allPermissions = Permission::getAllPermissions();

        return Inertia::render('Roles/Show', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                'type' => $role->type,
                'is_system_role' => $role->is_system_role,
                'permissions' => $role->permissions ?? [],
                'users_count' => $users->count(),
                'can_be_deleted' => $role->canBeDeleted(),
            ],
            'users' => $users,
            'allPermissions' => $allPermissions,
        ]);
    }

    /**
     * Show the form for editing the specified role
     */
    public function edit(Role $role)
    {
        if ($role->is_system_role) {
            return redirect()->route('roles.index')->with('error', 'System roles cannot be edited.');
        }

        $allPermissions = Permission::getAllPermissions();

        return Inertia::render('Roles/Edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                'type' => $role->type,
                'permissions' => $role->permissions ?? [],
            ],
            'allPermissions' => $allPermissions,
        ]);
    }

    /**
     * Update the specified role
     */
    public function update(Request $request, Role $role)
    {
        if ($role->is_system_role) {
            return back()->with('error', 'System roles cannot be modified.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,' . $role->id,
            'description' => 'nullable|string|max:500',
            'type' => 'required|in:admin,staff,custom',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',
        ]);

        $role->update([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'],
            'permissions' => $validated['permissions'] ?? [],
        ]);

        return redirect()->route('roles.index')->with('success', 'Role updated successfully.');
    }

    /**
     * Remove the specified role
     */
    public function destroy(Role $role)
    {
        if ($role->is_system_role) {
            return back()->with('error', 'System roles cannot be deleted.');
        }

        if (!$role->canBeDeleted()) {
            return back()->with('error', 'Cannot delete role that has users assigned to it.');
        }

        $role->delete();

        return redirect()->route('roles.index')->with('success', 'Role deleted successfully.');
    }

    /**
     * Toggle a specific permission for a role
     */
    public function togglePermission(Request $request, Role $role)
    {
        if ($role->is_system_role) {
            return back()->with('error', 'System roles cannot be modified.');
        }

        $request->validate([
            'permission' => 'required|string',
        ]);

        $permission = $request->permission;

        if ($role->hasPermission($permission)) {
            $role->revokePermission($permission);
            $message = 'Permission removed successfully.';
        } else {
            $role->grantPermission($permission);
            $message = 'Permission granted successfully.';
        }

        return back()->with('success', $message);
    }

    /**
     * Apply a preset of permissions to a role
     */
    public function applyPreset(Request $request, Role $role)
    {
        if ($role->is_system_role) {
            return back()->with('error', 'System roles cannot be modified.');
        }

        $request->validate([
            'preset' => 'required|in:admin,staff',
        ]);

        $role->setPresetPermissions($request->preset);

        return back()->with('success', 'Preset permissions applied successfully.');
    }
}
