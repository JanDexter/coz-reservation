# Role and Permission Management System

**Date:** November 16, 2025  
**Status:** ✅ Implemented and Seeded

## Overview
Implemented a comprehensive role and permission management system that allows administrators to:
- Create custom roles with specific permissions
- Assign roles to admin and staff users
- Manage individual user permissions (override or extend role permissions)
- Customer accounts are intentionally excluded from permission management

## Database Schema

### New Tables Created

#### `roles` table
- `id` - Primary key
- `name` - Role name (e.g., "Administrator", "Staff Member")
- `slug` - URL-friendly identifier (auto-generated from name)
- `description` - Role description
- `permissions` - JSON array of granted permissions
- `type` - Enum: 'admin', 'staff', 'custom'
- `is_system_role` - Boolean (system roles cannot be deleted)
- Timestamps

#### Updated Tables
- `admins` - Added `role_id` foreign key
- `staff` - Added `permissions` (JSON) and `role_id` foreign key

## Permission Hierarchy

The system implements a three-tier permission hierarchy:

1. **Super Admin** - Always has all permissions regardless of role or individual settings
2. **Role Permissions** - Base permissions inherited from assigned role
3. **Individual Permissions** - User-specific permissions that extend or override role permissions

## Permission Categories

Permissions are organized into 7 categories:

1. **user_management** - User CRUD, role management, activation/deactivation
2. **space_management** - Space CRUD, type management, availability
3. **reservation_management** - Reservation CRUD, cancellation, pricing overrides
4. **customer_management** - Customer CRUD, booking, transaction history
5. **financial** - Transaction viewing, payments, refunds, financial reports
6. **reports** - Dashboard analytics, reports, exports
7. **settings** - System settings, logs, backup/restore

## System Roles (Seeded)

### 1. Administrator
- **Type:** admin
- **System Role:** Yes
- **Permissions:** All permissions
- **Description:** Full system access with all permissions

### 2. Staff Member
- **Type:** staff  
- **System Role:** Yes
- **Permissions:** All permissions except:
  - `user_management.*`
  - `settings.manage_system_settings`
- **Description:** Standard staff access for daily operations

### 3. Staff (Read-Only)
- **Type:** staff
- **System Role:** No (can be deleted)
- **Permissions:** All `view_*` permissions only
- **Description:** View-only access for staff members

## Backend Implementation

### Models

#### `Role` Model (`app/Models/Role.php`)
**Methods:**
- `hasPermission($permission)` - Check if role has specific permission
- `grantPermission($permission)` - Add permission to role
- `revokePermission($permission)` - Remove permission from role
- `setPresetPermissions($preset)` - Apply permission preset ('all', 'staff', 'readonly')
- `canBeDeleted()` - Check if role can be safely deleted

**Relationships:**
- `admins()` - Users with admin profiles using this role
- `staff()` - Users with staff profiles using this role

#### Enhanced `Admin` Model
- Added `role()` relationship
- Enhanced `hasPermission()` to check role permissions first
- `getAllPermissions()` - Merges role and individual permissions

#### Enhanced `Staff` Model
- Added `permissions` JSON cast
- Added `role()` relationship
- `hasPermission()` checks role then individual permissions
- `getAllPermissions()` - Merges role and individual permissions

#### `Permission` Model  
**New Method:**
- `getAllPermissionKeys()` - Returns flat array of all permission keys (e.g., 'user_management.view_users')

### Controllers

#### `RoleController` (`app/Http/Controllers/RoleController.php`)
Full CRUD for role management:
- `index()` - List all roles with user counts
- `create()` - Show create role form
- `store()` - Create new role
- `show()` - View role details with assigned users
- `edit()` - Edit role (blocks system roles)
- `update()` - Update role
- `destroy()` - Delete role (blocks system roles and roles with users)
- `togglePermission()` - Toggle single permission on/off
- `applyPreset()` - Apply permission preset to role

#### `UserPermissionManagementController` (`app/Http/Controllers/UserPermissionManagementController.php`)
Manage permissions for individual users:
- `edit($user)` - Show permission management interface
- `update($user)` - Update user permissions
- `togglePermission($user)` - Toggle single permission
- `applyPreset($user)` - Apply permission preset
- `assignRole($user)` - Assign role to user

### Routes (`routes/web.php`)

All routes are under `can:manage-users` middleware:

```php
// Role management
Route::resource('roles', RoleController::class);
Route::post('roles/{role}/toggle-permission', [RoleController::class, 'togglePermission']);
Route::post('roles/{role}/apply-preset', [RoleController::class, 'applyPreset']);

// User permission management
Route::get('permissions/users/{user}', [UserPermissionManagementController::class, 'edit']);
Route::put('permissions/users/{user}', [UserPermissionManagementController::class, 'update']);
Route::post('permissions/users/{user}/toggle', [UserPermissionManagementController::class, 'togglePermission']);
Route::post('permissions/users/{user}/preset', [UserPermissionManagementController::class, 'applyPreset']);
Route::post('permissions/users/{user}/assign-role', [UserPermissionManagementController::class, 'assignRole']);
```

## Frontend Implementation

### Vue Components

#### 1. `Roles/Index.vue`
Lists all roles with:
- Role name, type, permission count, user count
- "System Role" badge for protected roles
- View, Edit, Delete actions (delete blocked for system roles or roles with users)
- Pagination support
- Link to create new role

#### 2. `Roles/Create.vue`
Create new role with:
- Name, description, type (admin/staff/custom)
- Permission selection grouped by category
- Quick presets: All Permissions, Staff Default, Read Only, Clear All
- Visual permission checkboxes organized by category

#### 3. `Roles/Edit.vue`
Edit existing role (similar to Create):
- Blocks editing of system role name/type
- Shows warning for system roles
- Displays user count affected by changes
- Same permission interface as Create

#### 4. `Roles/Show.vue`
View role details:
- Role information (name, type, description, status)
- All assigned permissions grouped by category
- List of users assigned to this role
- Edit button (hidden for system roles)

#### 5. `UserPermissions/Edit.vue`
Manage individual user permissions:
- User info display
- Role assignment dropdown with available roles
- Shows which permissions come from role (blue highlight)
- Individual permission checkboxes
- Total permission count breakdown
- Quick presets for individual permissions

### Permission Display Format

Permissions are formatted for display:
- Category prefix removed (e.g., `user_management.view_users` → "view users")
- Underscores replaced with spaces
- Grouped by category with headers

## Seeder

### `RoleSeeder` (`database/seeders/RoleSeeder.php`)
Seeds the three initial system roles. Run with:
```bash
php artisan db:seed --class=RoleSeeder
```

## Usage Examples

### Check Permission in Controller
```php
// Check if user has specific permission
if (auth()->user()->admin->hasPermission('user_management.create_users')) {
    // Allow user creation
}

// Get all user permissions (role + individual)
$permissions = auth()->user()->admin->getAllPermissions();
```

### Check Permission in Blade/Vue
```php
@can('create_users')
    <!-- Show create button -->
@endcan
```

### Assign Role to User
```php
$role = Role::where('slug', 'staff-member')->first();
$admin = Admin::find($userId);
$admin->role_id = $role->id;
$admin->save();
```

### Grant Individual Permission
```php
$staff = Staff::find($userId);
$staff->grantPermission('financial.issue_refunds');
```

## Security Considerations

1. **System Roles Protected** - Cannot be deleted or renamed
2. **Customer Exclusion** - Customers cannot have roles or custom permissions assigned
3. **Role Deletion** - Blocked if any users are assigned to the role
4. **Super Admin** - Always bypasses permission checks
5. **Permission Validation** - All permission strings validated against `Permission::getAllPermissionKeys()`

## Testing Checklist

- [ ] Create custom role with specific permissions
- [ ] Assign role to admin user
- [ ] Assign role to staff user  
- [ ] Test role permissions work correctly
- [ ] Grant individual permission to user
- [ ] Verify individual permission overrides role
- [ ] Try to delete system role (should fail)
- [ ] Try to edit system role (should show warning)
- [ ] Try to delete role with assigned users (should fail)
- [ ] Verify customers cannot be assigned roles
- [ ] Test permission presets (All, Staff, Read-Only)
- [ ] Test permission toggle functionality
- [ ] Verify super admin always has access

## Next Steps

### Recommended Enhancements
1. Add role management link to navigation menu
2. Update UserManagementController to show user's role in user list
3. Add role filter in user management index
4. Create activity log for permission changes
5. Add permission dependency system (e.g., 'edit' requires 'view')
6. Implement permission groups/templates
7. Add bulk user role assignment
8. Create permission audit report

### Navigation Update Needed
Add to admin navigation menu:
```vue
<Link :href="route('roles.index')">
    Role Management
</Link>
```

## Migration History

1. `2025_11_16_211731_add_permissions_to_staff_table.php` - Added permissions column to staff
2. `2025_11_16_211820_create_roles_table.php` - Created roles table
3. `2025_11_16_211924_add_role_id_to_admin_and_staff_tables.php` - Added role_id foreign keys

All migrations completed successfully on November 16, 2025.

## Files Created/Modified

### New Files
- `app/Models/Role.php`
- `app/Http/Controllers/RoleController.php`
- `app/Http/Controllers/UserPermissionManagementController.php`
- `resources/js/Pages/Roles/Index.vue`
- `resources/js/Pages/Roles/Create.vue`
- `resources/js/Pages/Roles/Edit.vue`
- `resources/js/Pages/Roles/Show.vue`
- `resources/js/Pages/UserPermissions/Edit.vue`
- `database/seeders/RoleSeeder.php`
- `database/migrations/2025_11_16_211731_add_permissions_to_staff_table.php`
- `database/migrations/2025_11_16_211820_create_roles_table.php`
- `database/migrations/2025_11_16_211924_add_role_id_to_admin_and_staff_tables.php`

### Modified Files
- `app/Models/Admin.php` - Added role relationship and enhanced permission checks
- `app/Models/Staff.php` - Added permissions, role relationship, permission methods
- `app/Models/Permission.php` - Added `getAllPermissionKeys()` method
- `routes/web.php` - Added role and permission management routes

---

**System Status:** ✅ Fully Implemented  
**Database Status:** ✅ Migrated and Seeded  
**Frontend Status:** ✅ Components Created  
**Testing Status:** ⏳ Pending User Testing
