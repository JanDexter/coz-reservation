<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Migrate existing users to appropriate tables
        // Get all admin users and create admin records
        $adminUsers = DB::table('users')->where('role', 'admin')->get();
        foreach ($adminUsers as $user) {
            DB::table('admins')->insert([
                'user_id' => $user->id,
                'permission_level' => 'super_admin',
                'permissions' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Get all staff users and create staff records
        $staffUsers = DB::table('users')->where('role', 'staff')->get();
        foreach ($staffUsers as $user) {
            DB::table('staff')->insert([
                'user_id' => $user->id,
                'employee_id' => 'EMP' . str_pad($user->id, 5, '0', STR_PAD_LEFT),
                'department' => null,
                'hourly_rate' => null,
                'hired_date' => $user->created_at,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Get all customer users and ensure they have customer records
        $customerUsers = DB::table('users')->where('role', 'customer')->get();
        foreach ($customerUsers as $user) {
            // Check if customer record already exists
            $existingCustomer = DB::table('customers')->where('user_id', $user->id)->first();
            
            if (!$existingCustomer) {
                // Create customer record if it doesn't exist
                DB::table('customers')->insert([
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone ?? '',
                    'company_name' => $user->company_name ?? null,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Step 2: Remove role column from users table
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Step 1: Add role column back
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['customer', 'staff', 'admin'])->default('customer')->after('email');
        });

        // Step 2: Restore role data from specialized tables
        $admins = DB::table('admins')->get();
        foreach ($admins as $admin) {
            DB::table('users')->where('id', $admin->user_id)->update(['role' => 'admin']);
        }

        $staff = DB::table('staff')->get();
        foreach ($staff as $staffMember) {
            DB::table('users')->where('id', $staffMember->user_id)->update(['role' => 'staff']);
        }

        $customers = DB::table('customers')->whereNotNull('user_id')->get();
        foreach ($customers as $customer) {
            DB::table('users')->where('id', $customer->user_id)->update(['role' => 'customer']);
        }
    }
};
