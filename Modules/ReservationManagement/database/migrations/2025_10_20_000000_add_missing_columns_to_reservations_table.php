<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // Add user_id if it doesn't exist
            if (!Schema::hasColumn('reservations', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            }
            
            // Add start_time if it doesn't exist
            if (!Schema::hasColumn('reservations', 'start_time')) {
                $table->timestamp('start_time')->nullable()->after('hold_until');
            }
            
            // Add end_time if it doesn't exist
            if (!Schema::hasColumn('reservations', 'end_time')) {
                $table->timestamp('end_time')->nullable()->after('start_time');
            }
            
            // Add applied_hourly_rate if it doesn't exist
            if (!Schema::hasColumn('reservations', 'applied_hourly_rate')) {
                $table->decimal('applied_hourly_rate', 10, 2)->default(0)->after('end_time');
            }
            
            // Add applied_discount_hours if it doesn't exist
            if (!Schema::hasColumn('reservations', 'applied_discount_hours')) {
                $table->unsignedInteger('applied_discount_hours')->nullable()->after('applied_hourly_rate');
            }
            
            // Add applied_discount_percentage if it doesn't exist
            if (!Schema::hasColumn('reservations', 'applied_discount_percentage')) {
                $table->decimal('applied_discount_percentage', 5, 2)->nullable()->after('applied_discount_hours');
            }
            
            // Add is_discounted if it doesn't exist
            if (!Schema::hasColumn('reservations', 'is_discounted')) {
                $table->boolean('is_discounted')->default(false)->after('applied_discount_percentage');
            }
            
            // Add cost if it doesn't exist
            if (!Schema::hasColumn('reservations', 'cost')) {
                $table->decimal('cost', 10, 2)->default(0)->after('is_discounted');
            }
            
            // Update status enum to include more statuses if needed
            if (Schema::hasColumn('reservations', 'status')) {
                $table->enum('status', ['paid', 'hold', 'active', 'completed', 'cancelled'])->default('hold')->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            if (Schema::hasColumn('reservations', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }
            if (Schema::hasColumn('reservations', 'start_time')) {
                $table->dropColumn('start_time');
            }
            if (Schema::hasColumn('reservations', 'end_time')) {
                $table->dropColumn('end_time');
            }
            if (Schema::hasColumn('reservations', 'applied_hourly_rate')) {
                $table->dropColumn('applied_hourly_rate');
            }
            if (Schema::hasColumn('reservations', 'applied_discount_hours')) {
                $table->dropColumn('applied_discount_hours');
            }
            if (Schema::hasColumn('reservations', 'applied_discount_percentage')) {
                $table->dropColumn('applied_discount_percentage');
            }
            if (Schema::hasColumn('reservations', 'is_discounted')) {
                $table->dropColumn('is_discounted');
            }
            if (Schema::hasColumn('reservations', 'cost')) {
                $table->dropColumn('cost');
            }
        });
    }
};
