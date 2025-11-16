<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('reservations')) {
            Schema::create('reservations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('space_type_id')->constrained()->onDelete('cascade');
                $table->enum('payment_method', ['gcash', 'maya', 'cash']);
                $table->unsignedInteger('hours')->default(1);
                $table->unsignedInteger('pax')->default(1);
                $table->enum('status', ['paid', 'hold'])->default('hold');
                $table->timestamp('hold_until')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
