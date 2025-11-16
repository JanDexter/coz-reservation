<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\UserPermissionController;
use App\Http\Controllers\SpaceManagementController;
use App\Http\Controllers\PublicReservationController;
use App\Http\Controllers\CustomerViewController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\AdminReservationController;
use App\Http\Controllers\RefundController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\PasswordChangeController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', CustomerViewController::class)->name('customer.view');
Route::get('/my-transactions', [CustomerViewController::class, 'transactions'])->middleware(['auth', 'verified'])->name('customer.transactions');
Route::get('/my-reservation-history', [CustomerViewController::class, 'reservationHistory'])->middleware(['auth', 'verified'])->name('customer.reservation.history');

// Password change routes
Route::post('/password/change/request', [PasswordChangeController::class, 'requestChange'])->middleware(['auth', 'verified'])->name('password.change.request');
Route::get('/password/change/verify', [PasswordChangeController::class, 'verifyToken'])->name('password.change.verify');
Route::post('/password/change/update', [PasswordChangeController::class, 'updatePassword'])->name('password.change.update');

Route::get('/auth/google', [GoogleAuthController::class, 'redirectToGoogle'])->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');

// Customer email verification
Route::get('/customer/verify-email/{customer}', [GoogleAuthController::class, 'verifyCustomerEmail'])
    ->middleware('signed')
    ->name('customer.verify-email');

// First-time admin setup routes - only accessible when no admin exists
Route::middleware('guest')->group(function () {
    Route::get('/setup', [SetupController::class, 'showSetupForm'])->name('setup.form');
    Route::post('/setup', [SetupController::class, 'storeAdmin'])->name('setup.store');
});

$adminPrefix = trim(config('app.admin_area_prefix', 'coz-control'), '/');

// PWA Routes
Route::get('/manifest.json', function () {
    return response()->file(public_path('manifest.json'), [
        'Content-Type' => 'application/json'
    ]);
});

Route::get('/sw.js', function () {
    return response()->file(public_path('sw.js'), [
        'Content-Type' => 'application/javascript'
    ]);
});

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

// Public reservation endpoints with rate limiting for security
Route::middleware(['throttle:60,1'])->group(function () {
    // Public reservation endpoint - requires authentication AND email verification
    Route::post('/public/reservations', [PublicReservationController::class, 'store'])
        ->middleware(['auth', 'verified'])
        ->name('public.reservations.store');

    // Check availability for specific time window - no auth required but rate limited
    Route::post('/public/check-availability', [PublicReservationController::class, 'checkAvailability'])
        ->name('public.check-availability');
});

// The registration routes are now handled by the controller and auth.php
// Route::any('/register', function () {
//     return redirect()->route('login')->with('status', 'Registration is disabled. Please ask the admin to create your account.');
// });

// Route::any($adminPrefix.'/register', function () {
//     return redirect()->route('login')->with('status', 'Registration is disabled. Please ask the admin to create your account.');
// });

// Authenticated admin area behind configurable prefix (admin only)
Route::middleware(['auth', 'can:admin-access'])->prefix($adminPrefix)->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // Customer management routes 
    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/create', [CustomerController::class, 'create'])->name('customers.create');
    Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    Route::get('customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
    Route::match(['put', 'patch'], 'customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    
    // Service management routes (Co-workspace reservations)
    Route::resource('services', ServiceController::class);
    Route::patch('services/{service}/close', [ServiceController::class, 'close'])->name('services.close');
    
    // User management routes (Admin only)
    Route::middleware('can:manage-users')->group(function () {
        Route::get('user-management', [UserManagementController::class, 'index'])->name('user-management.index');
        Route::get('user-management/create', [UserManagementController::class, 'create'])->name('user-management.create');
        Route::post('user-management', [UserManagementController::class, 'store'])->name('user-management.store');
        Route::get('user-management/{user}', [UserManagementController::class, 'show'])->name('user-management.show');
        Route::get('user-management/{user}/edit', [UserManagementController::class, 'edit'])->name('user-management.edit');
        Route::match(['put', 'patch'], 'user-management/{user}', [UserManagementController::class, 'update'])->name('user-management.update');
        Route::patch('user-management/{user}/toggle-status', [UserManagementController::class, 'toggleStatus'])->name('user-management.toggle-status');
        
        // Permission management routes (old controller - keep for backward compatibility)
        Route::get('user-management/{user}/permissions', [UserPermissionController::class, 'edit'])->name('user-permissions.edit');
        Route::put('user-management/{user}/permissions', [UserPermissionController::class, 'update'])->name('user-permissions.update');
        Route::post('user-management/{user}/permissions/preset', [UserPermissionController::class, 'applyPreset'])->name('user-permissions.apply-preset');
        Route::post('user-management/{user}/permissions/toggle', [UserPermissionController::class, 'togglePermission'])->name('user-permissions.toggle');
        
        // New permission management routes
        Route::get('permissions/users/{user}', [\App\Http\Controllers\UserPermissionManagementController::class, 'edit'])->name('permissions.users.edit');
        Route::put('permissions/users/{user}', [\App\Http\Controllers\UserPermissionManagementController::class, 'update'])->name('permissions.users.update');
        Route::post('permissions/users/{user}/toggle', [\App\Http\Controllers\UserPermissionManagementController::class, 'togglePermission'])->name('permissions.users.toggle');
        Route::post('permissions/users/{user}/preset', [\App\Http\Controllers\UserPermissionManagementController::class, 'applyPreset'])->name('permissions.users.preset');
        Route::post('permissions/users/{user}/assign-role', [\App\Http\Controllers\UserPermissionManagementController::class, 'assignRole'])->name('permissions.users.assign-role');
        
        // Role management routes
        Route::resource('roles', \App\Http\Controllers\RoleController::class);
        Route::post('roles/{role}/toggle-permission', [\App\Http\Controllers\RoleController::class, 'togglePermission'])->name('roles.toggle-permission');
        Route::post('roles/{role}/apply-preset', [\App\Http\Controllers\RoleController::class, 'applyPreset'])->name('roles.apply-preset');
    });
    
    // Space management routes (Admin only)
    Route::middleware('can:admin-access')->group(function () {
        Route::get('space-management', [SpaceManagementController::class, 'index'])->name('space-management.index');
    Route::patch('space-management/space-types/{spaceType}/details', [SpaceManagementController::class, 'updateDetails'])->name('space-management.update-details');
        Route::patch('space-management/space-types/{spaceType}/pricing', [SpaceManagementController::class, 'updatePricing'])->name('space-management.update-pricing');
        Route::patch('space-management/spaces/{space}/assign', [SpaceManagementController::class, 'assignSpace'])->name('space-management.assign-space');
        Route::patch('space-management/spaces/{space}/release', [SpaceManagementController::class, 'releaseSpace'])->name('space-management.release-space');
        Route::post('space-management/space-types', [SpaceManagementController::class, 'storeSpaceType'])->name('space-management.store-space-type');
        Route::post('space-management/space-types/{spaceType}/spaces', [SpaceManagementController::class, 'storeSpace'])->name('space-management.store-space');
        Route::delete('space-management/spaces/{space}', [SpaceManagementController::class, 'destroySpace'])->name('space-management.destroy-space');
        Route::delete('space-management/space-types/{spaceType}/spaces', [SpaceManagementController::class, 'bulkDestroySpaces'])->name('space-management.bulk-destroy-spaces');
        Route::delete('space-management/space-types/{spaceType}', [SpaceManagementController::class, 'destroySpaceType'])->name('space-management.destroy-space-type');
        Route::post('space-management/spaces/{space}/start-open-time', [SpaceManagementController::class, 'startOpenTime'])->name('space-management.start-open-time');
        Route::post('space-management/spaces/{space}/end-open-time', [SpaceManagementController::class, 'endOpenTime'])->name('space-management.end-open-time');
        
        // Payment routes
        Route::post('payments/reservations/{reservation}', [PaymentController::class, 'processPayment'])->name('payments.process');
        Route::post('payments/customers/{customer}', [PaymentController::class, 'processCustomerPayment'])->name('payments.customer');
        
        // Refund management routes
        Route::get('refunds', [RefundController::class, 'index'])->name('refunds.index');
        Route::post('refunds/{refund}/process', [RefundController::class, 'process'])->name('refunds.process');
        Route::post('refunds/{refund}/reject', [RefundController::class, 'reject'])->name('refunds.reject');
    });
    
    // Profile routes
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::match(['patch', 'put'], 'profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('calendar', [CalendarController::class, 'index'])->name('calendar');

    Route::put('reservations/{reservation}', [AdminReservationController::class, 'update'])->name('admin.reservations.update');
    Route::post('reservations/{reservation}/close', [AdminReservationController::class, 'close'])->name('admin.reservations.close');
    Route::post('reservations/{reservation}/cancel', [AdminReservationController::class, 'cancel'])->name('admin.reservations.cancel');
    Route::post('reservations/{reservation}/cancel', [AdminReservationController::class, 'cancel'])->name('admin.reservations.cancel');

    // Transaction routes
    Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('transactions/export', [TransactionController::class, 'export'])->name('transactions.export');
    Route::put('transactions/{reservation}', [TransactionController::class, 'update'])->name('transactions.update');
});

// Customer reservation management routes (authenticated customers)
Route::middleware('auth')->group(function () {
    Route::post('reservations/{reservation}/extend', [PublicReservationController::class, 'extend'])->name('reservations.extend');
    Route::post('reservations/{reservation}/end-early', [PublicReservationController::class, 'endEarly'])->name('reservations.end-early');
    Route::post('reservations/{reservation}/pay', [PaymentController::class, 'processPayment'])->name('customer.reservations.pay');
    Route::delete('reservations/{reservation}', [PublicReservationController::class, 'destroy'])->name('reservations.destroy');
});

require __DIR__.'/auth.php';

