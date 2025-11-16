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
        Schema::table('transaction_logs', function (Blueprint $table) {
            $table->string('type')->after('id'); // 'payment', 'refund', 'cancellation'
            $table->foreignId('reservation_id')->after('type')->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->after('reservation_id')->constrained()->onDelete('cascade');
            $table->foreignId('processed_by')->nullable()->after('customer_id')->constrained('users')->onDelete('set null');
            $table->decimal('amount', 10, 2)->after('processed_by');
            $table->string('payment_method')->nullable()->after('amount');
            $table->string('status')->after('payment_method');
            $table->string('reference_number')->nullable()->after('status');
            $table->text('description')->nullable()->after('reference_number');
            $table->text('notes')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transaction_logs', function (Blueprint $table) {
            $table->dropForeign(['reservation_id']);
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['processed_by']);
            $table->dropColumn([
                'type',
                'reservation_id',
                'customer_id',
                'processed_by',
                'amount',
                'payment_method',
                'status',
                'reference_number',
                'description',
                'notes',
            ]);
        });
    }
};
