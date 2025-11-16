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
        Schema::table('space_types', function (Blueprint $table) {
            $table->decimal('hourly_rate', 8, 2)->nullable()->after('default_price');
            $table->integer('default_discount_hours')->nullable()->after('hourly_rate')->comment('Default hours after which discount applies');
            $table->decimal('default_discount_percentage', 5, 2)->nullable()->after('default_discount_hours')->comment('Default discount percentage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('space_types', function (Blueprint $table) {
            $table->dropColumn(['hourly_rate', 'default_discount_hours', 'default_discount_percentage']);
        });
    }
};
