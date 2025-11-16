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
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['service_type', 'service_price', 'service_start_time', 'service_end_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('service_type')->nullable();
            $table->decimal('service_price', 8, 2)->nullable();
            $table->timestamp('service_start_time')->nullable();
            $table->timestamp('service_end_time')->nullable();
        });
    }
};
