<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n=== Checking Reservations for Pending Payments ===\n\n";

// Check all reservations with end_time and cost > 0
$allReservations = \App\Models\Reservation::whereNotNull('end_time')
    ->where('cost', '>', 0)
    ->orderByDesc('end_time')
    ->get(['id', 'status', 'start_time', 'end_time', 'cost', 'amount_paid']);

echo "Total reservations with end_time and cost > 0: " . $allReservations->count() . "\n\n";

foreach ($allReservations as $r) {
    $balance = $r->cost - ($r->amount_paid ?? 0);
    $isPending = $balance > 0;
    
    echo sprintf(
        "ID: %d | Status: %-10s | Cost: %8.2f | Paid: %8.2f | Balance: %8.2f | %s\n",
        $r->id,
        $r->status ?? 'NULL',
        $r->cost,
        $r->amount_paid ?? 0,
        $balance,
        $isPending ? '✗ UNPAID' : '✓ PAID'
    );
    echo "  Start: {$r->start_time} | End: {$r->end_time}\n\n";
}

echo "\n=== Current Query Logic ===\n\n";

// Test the current query
$pendingPayments = \App\Models\Reservation::query()
    ->whereNotIn('status', ['paid', 'cancelled'])
    ->whereNotNull('end_time')
    ->where('cost', '>', 0)
    ->where(function($q) {
        $q->whereNull('amount_paid')
          ->orWhereRaw('amount_paid < cost');
    })
    ->orderByDesc('end_time')
    ->get(['id', 'status', 'cost', 'amount_paid']);

echo "Pending payments found by query: " . $pendingPayments->count() . "\n\n";

foreach ($pendingPayments as $p) {
    echo sprintf(
        "ID: %d | Status: %-10s | Cost: %8.2f | Paid: %8.2f | Balance: %8.2f\n",
        $p->id,
        $p->status ?? 'NULL',
        $p->cost,
        $p->amount_paid ?? 0,
        $p->cost - ($p->amount_paid ?? 0)
    );
}

echo "\n=== End ===\n";
