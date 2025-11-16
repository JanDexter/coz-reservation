<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update any old status values to match the new enum
        DB::statement("UPDATE reservations SET status = 'on_hold' WHERE status = 'hold'");
        DB::statement("UPDATE reservations SET status = 'pending' WHERE status NOT IN ('pending', 'on_hold', 'confirmed', 'active', 'paid', 'completed', 'cancelled')");
        
        // For SQLite compatibility, use Laravel's schema builder
        if (Schema::hasColumn('reservations', 'status')) {
            Schema::table('reservations', function (Blueprint $table) {
                $table->enum('status', [
                    'pending',
                    'on_hold',
                    'confirmed',
                    'active',
                    'paid',
                    'partial',
                    'completed',
                    'cancelled'
                ])->default('pending')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // Revert to previous enum values
            $table->enum('status', [
                'pending',
                'on_hold',
                'confirmed',
                'active',
                'paid',
                'completed',
                'cancelled'
            ])->default('pending')->change();
        });
    }
};
