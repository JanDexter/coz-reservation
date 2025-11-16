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
            $table->string('pricing_type')->default('per_person')->after('hourly_rate')
                ->comment('Pricing type: per_person or per_reservation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('space_types', function (Blueprint $table) {
            $table->dropColumn('pricing_type');
        });
    }
};
