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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Service name (e.g., "Hot Desk", "Private Office", "Meeting Room")
            $table->text('description')->nullable(); // Service description
            $table->string('location')->nullable(); // Workspace location/room
            $table->decimal('price_per_hour', 8, 2)->nullable(); // Hourly rate
            $table->decimal('price_per_day', 8, 2)->nullable(); // Daily rate
            $table->decimal('price_per_month', 8, 2)->nullable(); // Monthly rate
            $table->integer('capacity')->default(1); // Max number of people
            $table->json('amenities')->nullable(); // Available amenities (wifi, parking, etc.)
            $table->json('availability_hours')->nullable(); // Operating hours
            $table->enum('status', ['active', 'reserved', 'closed'])->default('active');
            $table->foreignId('customer_id')->nullable()->constrained()->onDelete('set null'); // Who reserved it
            $table->foreignId('user_id')->constrained(); // Admin who manages this service
            $table->datetime('reserved_from')->nullable(); // Reservation start
            $table->datetime('reserved_until')->nullable(); // Reservation end
            $table->text('notes')->nullable(); // Additional notes
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
