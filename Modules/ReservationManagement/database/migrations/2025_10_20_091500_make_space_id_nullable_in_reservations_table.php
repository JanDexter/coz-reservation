<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{    public function up(): void
    {
        if (!Schema::hasTable('reservations')) {
            return;
        }

        if (Schema::hasColumn('reservations', 'space_id')) {
            Schema::table('reservations', function (Blueprint $table) {
                $table->dropForeign(['space_id']);
            });

            // SQLite-compatible approach
            Schema::table('reservations', function (Blueprint $table) {
                $table->unsignedBigInteger('space_id')->nullable()->change();
            });

            Schema::table('reservations', function (Blueprint $table) {
                $table->foreign('space_id')->references('id')->on('spaces')->nullOnDelete();
            });
        }
    }    public function down(): void
    {
        if (!Schema::hasTable('reservations')) {
            return;
        }

        if (Schema::hasColumn('reservations', 'space_id')) {
            Schema::table('reservations', function (Blueprint $table) {
                $table->dropForeign(['space_id']);
            });

            // SQLite-compatible approach
            Schema::table('reservations', function (Blueprint $table) {
                $table->unsignedBigInteger('space_id')->nullable(false)->change();
            });

            Schema::table('reservations', function (Blueprint $table) {
                $table->foreign('space_id')->references('id')->on('spaces')->cascadeOnDelete();
            });
        }
    }
};
