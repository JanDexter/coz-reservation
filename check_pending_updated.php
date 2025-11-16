<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n=== Testing UPDATED Query Logic ===\n\n";

// Test the NEW query (only excludes cancelled, checks balance directly)
$pendingPayments = \App\Models\Reservation::query()
    ->whereNotNull('end_time')
    ->where('cost', '>', 0)
    ->where(function($q) {
        $q->whereNull('amount_paid')
          ->orWhereRaw('amount_paid < cost');
    })
    ->whereNot('status', 'cancelled')  // Only exclude cancelled
    ->orderByDesc('end_time')
    ->get(['id', 'status', 'cost', 'amount_paid', 'end_time']);

echo "Pending payments found by NEW query: " . $pendingPayments->count() . "\n\n";

foreach ($pendingPayments as $p) {
    echo sprintf(
        "ID: %2d | Status: %-10s | Cost: %8.2f | Paid: %8.2f | Balance: %8.2f | End: %s\n",
        $p->id,
        $p->status ?? 'NULL',
        $p->cost,
        $p->amount_paid ?? 0,
        $p->cost - ($p->amount_paid ?? 0),
        $p->end_time
    );
}

echo "\n=== Summary ===\n";
echo "Total Unpaid Balance: ₱" . number_format($pendingPayments->sum(function($p) {
    return $p->cost - ($p->amount_paid ?? 0);
}), 2) . "\n";

echo "\n=== End ===\n";
