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
        // Drop each legacy column only if it exists to avoid migration errors
        if (Schema::hasColumn('customers', 'service_type')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('service_type');
            });
        }
        if (Schema::hasColumn('customers', 'service_price')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('service_price');
            });
        }
        if (Schema::hasColumn('customers', 'service_start_time')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('service_start_time');
            });
        }
        if (Schema::hasColumn('customers', 'service_end_time')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('service_end_time');
            });
        }
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
