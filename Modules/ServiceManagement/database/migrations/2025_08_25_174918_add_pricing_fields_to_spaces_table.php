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
        Schema::table('spaces', function (Blueprint $table) {
            $table->decimal('hourly_rate', 8, 2)->nullable()->after('notes');
            $table->integer('discount_hours')->nullable()->after('hourly_rate')->comment('Hours after which discount applies');
            $table->decimal('discount_percentage', 5, 2)->nullable()->after('discount_hours')->comment('Discount percentage for extended hours');
            $table->json('custom_rates')->nullable()->after('discount_percentage')->comment('Custom rates for specific time periods');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spaces', function (Blueprint $table) {
            $table->dropColumn(['hourly_rate', 'discount_hours', 'discount_percentage', 'custom_rates']);
        });
    }
};
