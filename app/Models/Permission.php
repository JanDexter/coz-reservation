<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'category',
    ];

    /**
     * Permission categories
     */
    const CATEGORY_USER_MANAGEMENT = 'user_management';
    const CATEGORY_SPACE_MANAGEMENT = 'space_management';
    const CATEGORY_RESERVATION_MANAGEMENT = 'reservation_management';
    const CATEGORY_FINANCIAL = 'financial';
    const CATEGORY_CUSTOMER_MANAGEMENT = 'customer_management';
    const CATEGORY_REPORTS = 'reports';
    const CATEGORY_SETTINGS = 'settings';

    /**
     * Get all available permissions with categories
     */
    public static function getAllPermissions(): array
    {
        return [
            self::CATEGORY_USER_MANAGEMENT => [
                'view_users' => 'View all users',
                'create_users' => 'Create new users',
                'edit_users' => 'Edit user details',
                'delete_users' => 'Delete users',
                'manage_user_roles' => 'Manage user roles and permissions',
                'activate_deactivate_users' => 'Activate/deactivate users',
            ],
            self::CATEGORY_SPACE_MANAGEMENT => [
                'view_spaces' => 'View all spaces',
                'create_spaces' => 'Create new spaces',
                'edit_spaces' => 'Edit space details',
                'delete_spaces' => 'Delete spaces',
                'manage_space_types' => 'Manage space types and pricing',
                'view_space_availability' => 'View space availability',
            ],
            self::CATEGORY_RESERVATION_MANAGEMENT => [
                'view_all_reservations' => 'View all reservations',
                'create_reservations' => 'Create reservations for customers',
                'edit_reservations' => 'Edit reservation details',
                'cancel_reservations' => 'Cancel reservations',
                'extend_reservations' => 'Extend reservation time',
                'override_pricing' => 'Override reservation pricing',
            ],
            self::CATEGORY_CUSTOMER_MANAGEMENT => [
                'view_customers' => 'View all customers',
                'create_customers' => 'Create new customers',
                'edit_customers' => 'Edit customer details',
                'view_customer_history' => 'View customer transaction history',
                'book_spaces' => 'Book spaces (as customer)',
            ],
            self::CATEGORY_FINANCIAL => [
                'view_transactions' => 'View all transactions',
                'process_payments' => 'Process payments',
                'issue_refunds' => 'Issue refunds',
                'approve_refunds' => 'Approve refund requests',
                'view_financial_reports' => 'View financial reports',
                'export_financial_data' => 'Export financial data',
            ],
            self::CATEGORY_REPORTS => [
                'view_dashboard' => 'View dashboard analytics',
                'view_reports' => 'View system reports',
                'export_reports' => 'Export reports',
                'view_analytics' => 'View advanced analytics',
            ],
            self::CATEGORY_SETTINGS => [
                'manage_system_settings' => 'Manage system settings',
                'view_logs' => 'View system logs',
                'backup_restore' => 'Backup and restore data',
            ],
        ];
    }

    /**
     * Get a flat array of all permission keys
     */
    public static function getAllPermissionKeys(): array
    {
        $permissions = [];
        foreach (self::getAllPermissions() as $category => $perms) {
            foreach ($perms as $key => $description) {
                $permissions[] = $category . '.' . $key;
            }
        }
        return $permissions;
    }

    /**
     * Get preset role permissions
     */
    public static function getPresetPermissions(string $roleType): array
    {
        $presets = [
            'admin' => [
                // Full system access except deletion
                'view_users', 'create_users', 'edit_users', 'manage_user_roles', 'activate_deactivate_users',
                'view_spaces', 'create_spaces', 'edit_spaces', 'manage_space_types', 'view_space_availability',
                'view_all_reservations', 'create_reservations', 'edit_reservations', 'cancel_reservations', 'extend_reservations',
                'view_customers', 'create_customers', 'edit_customers', 'view_customer_history',
                'view_transactions', 'process_payments', 'issue_refunds', 'approve_refunds', 'view_financial_reports',
                'view_dashboard', 'view_reports', 'export_reports', 'view_analytics',
                'manage_system_settings', 'view_logs',
            ],
            'staff' => [
                // Operational access
                'view_spaces', 'view_space_availability',
                'view_all_reservations', 'create_reservations', 'edit_reservations', 'extend_reservations',
                'view_customers', 'create_customers', 'edit_customers', 'view_customer_history',
                'view_transactions', 'process_payments',
                'view_dashboard', 'view_reports',
            ],
            'customer' => [
                // Customer-only access
                'book_spaces',
                'view_space_availability',
            ],
        ];

        return $presets[$roleType] ?? [];
    }

    /**
     * Format category name for display
     */
    public static function formatCategory(string $category): string
    {
        return ucwords(str_replace('_', ' ', $category));
    }
}
