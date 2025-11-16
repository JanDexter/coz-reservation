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
        if (!Schema::hasColumn('space_types', 'description')) {
            Schema::table('space_types', function (Blueprint $table) {
                $table->text('description')->nullable()->after('name');
            });
        }

        if (!Schema::hasColumn('space_types', 'image_path')) {
            Schema::table('space_types', function (Blueprint $table) {
                $table->string('image_path')->nullable()->after('description');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('space_types', 'image_path')) {
            Schema::table('space_types', function (Blueprint $table) {
                $table->dropColumn('image_path');
            });
        }

        if (Schema::hasColumn('space_types', 'description')) {
            Schema::table('space_types', function (Blueprint $table) {
                $table->dropColumn('description');
            });
        }
    }
};
