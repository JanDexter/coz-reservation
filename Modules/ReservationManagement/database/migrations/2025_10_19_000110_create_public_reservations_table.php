<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('public_reservations')) {
            Schema::create('public_reservations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('space_type_id')->constrained()->onDelete('cascade');
                $table->string('payment_method', 16); // gcash | maya | cash
                $table->unsignedInteger('hours')->default(1);
                $table->unsignedInteger('pax')->default(1);
                $table->string('status', 16)->default('hold'); // paid | hold
                $table->timestamp('hold_until')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('public_reservations');
    }
};
