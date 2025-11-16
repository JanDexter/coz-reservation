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
        Schema::create('spaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_type_id')->constrained()->onDelete('cascade');
            $table->string('name'); // 'Private Space 1', 'Drafting Table A', etc.
            $table->string('status')->default('available'); // available, occupied, maintenance
            $table->foreignId('current_customer_id')->nullable()->constrained('customers')->onDelete('set null');
            $table->datetime('occupied_from')->nullable();
            $table->datetime('occupied_until')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spaces');
    }
};
