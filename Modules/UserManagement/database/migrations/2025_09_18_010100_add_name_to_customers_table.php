<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddNameToCustomersTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'name')) {
                $table->string('name')->nullable()->after('id');
            }
        });

        // Backfill name from contact_person or company_name
        DB::statement("UPDATE customers SET name = COALESCE(NULLIF(name, ''), NULLIF(contact_person, ''), company_name) WHERE name IS NULL OR name = ''");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'name')) {
                $table->dropColumn('name');
            }
        });
    }
}

return new AddNameToCustomersTable;
