# Database Migration Organization Plan

## 📊 Current State
All migrations are in `database/migrations/`. They need to be **copied** (not moved) to respective modules to maintain database compatibility while enabling modular structure.

## 🗂️ Migration Mapping by Module

### 1️⃣ UserManagement Module
**Location**: `Modules/UserManagement/database/migrations/`

**Migrations to copy**:
- `0001_01_01_000000_create_users_table.php`
- `2025_07_29_031442_add_role_to_users_table.php`
- `2025_10_19_082327_add_google_id_to_users_and_customers_tables.php`
- `2025_10_19_134029_add_two_factor_fields_to_users_table.php`
- `2025_10_20_175643_add_phone_to_users_table.php`
- `2025_10_21_000000_add_phone_to_users_table.php`
- `2025_11_10_164537_create_staff_table.php`
- `2025_11_10_164600_create_admins_table.php`
- `2025_11_10_164618_remove_role_from_users_table.php`
- `2025_11_11_000000_create_permissions_table.php` (if exists)
- `2025_11_11_000001_create_admin_permissions_table.php` (if exists)
- `2025_11_10_011905_create_email_verification_otps_table.php`
- `2025_07_28_160641_create_customers_table.php`

### 2️⃣ ServiceManagement Module
**Location**: `Modules/ServiceManagement/database/migrations/`

**Migrations to copy**:
- `2025_07_29_022258_create_services_table.php`
- `2025_08_25_172529_create_space_types_table.php`
- `2025_08_25_172553_create_spaces_table.php`
- `2025_07_29_025548_add_service_fields_to_customers_table.php`
- `2025_08_07_012218_add_user_id_to_customers_table.php`
- `2025_08_25_173136_add_space_type_id_to_customers_table.php`
- `2025_08_25_174918_add_pricing_fields_to_spaces_table.php`
- `2025_08_25_174951_add_pricing_fields_to_space_types_table.php`

### 3️⃣ ReservationManagement Module
**Location**: `Modules/ReservationManagement/database/migrations/`

**Migrations to copy**:
- `2025_09_16_124458_create_reservations_table.php`
- `2025_10_19_000000_create_reservations_table.php`
- `2025_10_19_000110_create_public_reservations_table.php`
- `2025_09_16_123128_standardize_company_name_in_customers_table.php`
- Any reservation-related modifications

### 4️⃣ BillingPayment Module
**Location**: `Modules/BillingPayment/database/migrations/`

**Migrations to copy**:
- `2025_11_06_033046_create_transaction_logs_table.php`
- Any payment-related tables (if they exist)

### 5️⃣ RefundManagement Module
**Location**: `Modules/RefundManagement/database/migrations/`

**Migrations to copy**:
- `2025_11_06_000000_create_refunds_table.php`

### 6️⃣ System Core (Laravel Framework)
**Keep in main `database/migrations/`**:
- `0001_01_01_000001_create_cache_table.php`
- `0001_01_01_000002_create_jobs_table.php`
- `2025_07_29_060253_create_personal_access_tokens_table.php` (Sanctum)

### 7️⃣ Deprecated/Task Tracker (Keep but don't move)
- `2025_07_28_160646_create_tasks_table.php` (deprecated)

---

## 🔄 Migration Strategy

### Option 1: Copy Migrations (RECOMMENDED) ✅
**Pros**:
- Maintains database compatibility
- Original migrations remain in place
- Modules have their own copy for reference
- Safe for existing production databases
- Can run `php artisan migrate` without issues

**Cons**:
- Duplicated files
- Need to keep both in sync if modifications needed

### Option 2: Move Migrations (NOT RECOMMENDED for production) ⚠️
**Pros**:
- No duplicate files
- Cleaner directory structure

**Cons**:
- Laravel won't automatically find module migrations
- Requires custom migration loading configuration
- Risk of breaking existing deployments
- Complex migration path management

---

## 📝 Implementation Steps

### Step 1: Copy migrations to modules (SAFE APPROACH)

```powershell
# UserManagement
Copy-Item "database/migrations/0001_01_01_000000_create_users_table.php" -Destination "Modules/UserManagement/database/migrations/"
Copy-Item "database/migrations/*users*.php" -Destination "Modules/UserManagement/database/migrations/"
Copy-Item "database/migrations/*staff*.php" -Destination "Modules/UserManagement/database/migrations/"
Copy-Item "database/migrations/*admin*.php" -Destination "Modules/UserManagement/database/migrations/"
Copy-Item "database/migrations/*customers*.php" -Destination "Modules/UserManagement/database/migrations/"
Copy-Item "database/migrations/*permission*.php" -Destination "Modules/UserManagement/database/migrations/"
Copy-Item "database/migrations/*email_verification*.php" -Destination "Modules/UserManagement/database/migrations/"

# ServiceManagement
Copy-Item "database/migrations/*service*.php" -Destination "Modules/ServiceManagement/database/migrations/"
Copy-Item "database/migrations/*space*.php" -Destination "Modules/ServiceManagement/database/migrations/"

# ReservationManagement
Copy-Item "database/migrations/*reservation*.php" -Destination "Modules/ReservationManagement/database/migrations/"

# BillingPayment
Copy-Item "database/migrations/*transaction*.php" -Destination "Modules/BillingPayment/database/migrations/"
Copy-Item "database/migrations/*payment*.php" -Destination "Modules/BillingPayment/database/migrations/"

# RefundManagement
Copy-Item "database/migrations/*refund*.php" -Destination "Modules/RefundManagement/database/migrations/"
```

### Step 2: Update config/modules.php (if needed)

Enable module migrations:
```php
'migrations' => true,
```

### Step 3: Register module migrations

Add to module service providers if needed:
```php
$this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
```

---

## ⚙️ Configuration for Module Migrations

### Update `config/modules.php`:

```php
'scan' => [
    'enabled' => true,
    'paths' => [
        base_path('Modules/*/*'),
    ],
],

'register' => [
    'translations' => true,
    'files' => 'register',
],

'activators' => [
    'file' => [
        'class' => \Nwidart\Modules\Activators\FileActivator::class,
        'statuses-file' => base_path('modules_statuses.json'),
        'cache-key' => 'activator.installed',
        'cache-lifetime' => 604800,
    ],
],
```

---

## 🎯 Recommended Approach

**For your current setup, I recommend**:

1. ✅ **COPY migrations to modules** (keep originals in `database/migrations/`)
2. ✅ Add migration loading to module service providers
3. ✅ Keep `php artisan migrate` working from root
4. ✅ Document which migrations belong to which module
5. ✅ Future migrations go directly into module folders

**Do NOT move/delete** original migrations yet - this maintains backwards compatibility and prevents deployment issues.

---

## 📊 Migration Organization Summary

| Module | Migration Count | Core Tables |
|--------|----------------|-------------|
| **UserManagement** | ~13 | users, admins, staff, customers, permissions, email_verification_otps |
| **ServiceManagement** | ~8 | services, spaces, space_types |
| **ReservationManagement** | ~3 | reservations, public_reservations |
| **BillingPayment** | ~1-2 | transaction_logs, payments |
| **RefundManagement** | ~1 | refunds |
| **System Core** | ~3 | cache, jobs, personal_access_tokens |

**Total**: ~30 migration files to organize

---

## 🚀 Next Steps

1. Run the copy commands to organize migrations into modules
2. Update module service providers to load migrations
3. Test `php artisan migrate` still works
4. Document module-specific migrations
5. Future database changes go directly into module folders

**Status**: Ready to execute migration copying
