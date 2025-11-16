<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->decimal('applied_hourly_rate', 10, 2)->nullable()->after('custom_hourly_rate');
            $table->unsignedInteger('applied_discount_hours')->nullable()->after('applied_hourly_rate');
            $table->decimal('applied_discount_percentage', 5, 2)->nullable()->after('applied_discount_hours');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['applied_hourly_rate', 'applied_discount_hours', 'applied_discount_percentage']);
        });
    }
};
