# Module Migration Plan

## ✅ Completed Steps

1. ✅ Installed `nwidart/laravel-modules` package
2. ✅ Published module configuration
3. ✅ Created 9 modules:
   - UserManagement
   - ServiceManagement
   - ReservationManagement
   - CalendarView
   - TimeManagement
   - CustomerBooking
   - BillingPayment
   - RefundManagement
   - WiFiCredentials
4. ✅ Updated `composer.json` with classmap autoloading for modules
5. ✅ Created Models directories in modules

## 📋 Module Structure Created

```
Modules/
├── UserManagement/
│   ├── app/
│   │   ├── Http/Controllers/
│   │   ├── Models/ (created)
│   │   └── Providers/
│   ├── database/
│   │   └── seeders/
│   ├── routes/
│   │   ├── web.php
│   │   └── api.php
│   └── module.json
├── [Similar structure for all other modules]
```

## 🔄 Migration Strategy

### Phase 1: Keep Backwards Compatibility
**Current Status: NOT STARTED**

Instead of moving files immediately, we'll create **facades/aliases** in modules that point to existing `App\Models` classes. This allows us to:
- Keep existing code working
- Gradually migrate routes and controllers
- Test each module independently

### Phase 2: Model Migration Mapping

| Model | Current Location | Target Module | Dependencies |
|-------|-----------------|---------------|--------------|
| `User.php` | `App\Models` | `UserManagement` | Admin, Staff, Customer, Permission, Service, Reservation |
| `Admin.php` | `App\Models` | `UserManagement` | User, Permission |
| `Staff.php` | `App\Models` | `UserManagement` | User |
| `Customer.php` | `App\Models` | `UserManagement` | User, SpaceType |
| `Permission.php` | `App\Models` | `UserManagement` | Admin |
| `Service.php` | `App\Models` | `ServiceManagement` | User, Space |
| `Space.php` | `App\Models` | `ServiceManagement` | SpaceType, Reservation |
| `SpaceType.php` | `App\Models` | `ServiceManagement` | Space, Customer |
| `Reservation.php` | `App\Models` | `ReservationManagement` | User, Space, Refund |
| `Refund.php` | `App\Models` | `RefundManagement` | Reservation, User |
| `TransactionLog.php` | `App\Models` | `BillingPayment` | User |
| `EmailVerificationOtp.php` | `App\Models` | `UserManagement` | User |

### Phase 3: Controller Migration Mapping

| Controller | Current Location | Target Module |
|------------|-----------------|---------------|
| `UserManagementController` | `App\Http\Controllers` | `UserManagement` |
| `UserPermissionController` | `App\Http\Controllers` | `UserManagement` |
| `ServiceController` | `App\Http\Controllers` | `ServiceManagement` |
| `SpaceManagementController` | `App\Http\Controllers` | `ServiceManagement` |
| `ReservationController` | `App\Http\Controllers` | `ReservationManagement` |
| `PublicReservationController` | `App\Http\Controllers` | `CustomerBooking` |
| `CalendarController` | `App\Http\Controllers` | `CalendarView` |
| `PaymentController` | `App\Http\Controllers` | `BillingPayment` |
| `RefundController` | `App\Http\Controllers` | `RefundManagement` |
| `TransactionController` | `App\Http\Controllers` | `BillingPayment` |

### Phase 4: Route Migration

Routes from `routes/web.php` will be split into module-specific route files:

**UserManagement** (`Modules/UserManagement/routes/web.php`):
```php
- /users/*
- /permissions/*
- /profile/*
- /setup/*
```

**ServiceManagement** (`Modules/ServiceManagement/routes/web.php`):
```php
- /services/*
- /spaces/*
- /space-types/*
```

**ReservationManagement** (`Modules/ReservationManagement/routes/web.php`):
```php
- /reservations/*
- /admin/reservations/*
```

**CustomerBooking** (`Modules/CustomerBooking/routes/web.php`):
```php
- /customer-view
- /public/reservations/*
```

**CalendarView** (`Modules/CalendarView/routes/web.php`):
```php
- /calendar
```

**BillingPayment** (`Modules/BillingPayment/routes/web.php`):
```php
- /payments/*
- /transactions/*
```

**RefundManagement** (`Modules/RefundManagement/routes/web.php`):
```php
- /refunds/*
```

### Phase 5: Migration Files

Create `database/migrations/` directories in each module and move relevant migrations:

**UserManagement**:
- `create_users_table.php`
- `create_admins_table.php`
- `create_staff_table.php`
- `create_customers_table.php`
- `create_permissions_table.php`
- `create_admin_permissions_table.php`

**ServiceManagement**:
- `create_services_table.php`
- `create_spaces_table.php`
- `create_space_types_table.php`

**ReservationManagement**:
- `create_reservations_table.php`

**BillingPayment**:
- `create_payments_table.php` (if exists)
- `create_transaction_logs_table.php` (if exists)

**RefundManagement**:
- `create_refunds_table.php`

## 🚧 Implementation Steps

### Step 1: Update bootstrap/app.php (NOT DONE)

Add module route loading:

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
    then: function () {
        // Load all module routes
        $modules = ['UserManagement', 'ServiceManagement', 'ReservationManagement', 
                   'CalendarView', 'TimeManagement', 'CustomerBooking', 
                   'BillingPayment', 'RefundManagement', 'WiFiCredentials'];
        
        foreach ($modules as $module) {
            $routeFile = base_path("Modules/{$module}/routes/web.php");
            if (file_exists($routeFile)) {
                Route::middleware('web')->group($routeFile);
            }
        }
    }
)
```

### Step 2: Create Model Aliases (NOT DONE)

In each module's Service Provider, add model aliases pointing to existing models until we fully migrate.

### Step 3: Migrate Routes Gradually (NOT DONE)

Move routes one module at a time, test, then move to next.

### Step 4: Update Vue Components (NOT DONE)

Update import paths in Vue components to use new module structure.

### Step 5: Move Migrations (NOT DONE)

Copy (don't move initially) migrations to module folders for future reference.

### Step 6: Test Everything (NOT DONE)

Run full test suite to ensure nothing broke.

## ⚠️ Critical Notes

1. **DO NOT delete old files** until migration is complete and tested
2. **Maintain backwards compatibility** during transition
3. **Test after each phase** before moving to next
4. **Update documentation** as we migrate

## 🎯 Current Status

**Phase 1 is ready to begin**

Next action: Update `bootstrap/app.php` to load module routes while keeping existing routes working.
