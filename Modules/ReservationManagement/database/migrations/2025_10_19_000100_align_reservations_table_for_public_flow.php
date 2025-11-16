<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('reservations')) {
            return;
        }

        Schema::table('reservations', function (Blueprint $table) {
            if (!Schema::hasColumn('reservations', 'space_type_id')) {
                $table->foreignId('space_type_id')->nullable()->constrained()->onDelete('cascade');
            }
            if (!Schema::hasColumn('reservations', 'payment_method')) {
                // Use string for broad DB compatibility (SQLite during tests)
                $table->string('payment_method', 16)->nullable()->index();
            }
            if (!Schema::hasColumn('reservations', 'hours')) {
                $table->unsignedInteger('hours')->default(1);
            }
            if (!Schema::hasColumn('reservations', 'pax')) {
                $table->unsignedInteger('pax')->default(1);
            }
            if (!Schema::hasColumn('reservations', 'status')) {
                $table->string('status', 16)->default('hold')->index();
            }
            if (!Schema::hasColumn('reservations', 'hold_until')) {
                $table->timestamp('hold_until')->nullable();
            }
            if (!Schema::hasColumn('reservations', 'notes')) {
                $table->text('notes')->nullable();
            }
        });
    }

    public function down(): void
    {
        // No-op to avoid data loss in down migrations.
    }
};
