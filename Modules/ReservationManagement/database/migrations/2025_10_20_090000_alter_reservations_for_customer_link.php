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
            // Make user_id nullable as reservations can be made by guests
            if (Schema::hasColumn('reservations', 'user_id')) {
                $table->foreignId('user_id')->nullable()->change();
            }

            // Add customer_id, which will be required
            if (!Schema::hasColumn('reservations', 'customer_id')) {
                $table->foreignId('customer_id')->after('user_id')->constrained()->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            if (Schema::hasColumn('reservations', 'customer_id')) {
                $table->dropForeign(['customer_id']);
                $table->dropColumn('customer_id');
            }
            
            // If you need to revert user_id to non-nullable, you'd do it here,
            // but be cautious as it might break guest reservations.
            // For this example, we'll leave it nullable on reversal.
        });
    }
};
