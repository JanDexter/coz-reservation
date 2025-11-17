<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Carbon\Carbon;
use App\Models\Reservation;

echo "=== TIMEZONE TEST ===\n\n";
echo "Config timezone: " . config('app.timezone') . "\n\n";

// Test 1: Parse timezone-naive string as Manila time
echo "TEST 1: Parsing '2025-11-17T13:30:00' as Manila time\n";
echo "-----------------------------------------------\n";

$testTime = "2025-11-17T13:30:00";
$carbon = Carbon::createFromFormat('Y-m-d\TH:i:s', $testTime, config('app.timezone'));

echo "Input string:  {$testTime}\n";
echo "Parsed as:     {$carbon->toDateTimeString()} ({$carbon->timezoneName})\n";
echo "UTC equiv:     {$carbon->copy()->setTimezone('UTC')->toDateTimeString()}\n";
echo "JSON output:   " . json_encode($carbon) . "\n\n";

// Test 2: Check latest reservation serialization
echo "TEST 2: Latest Reservation Serialization\n";
echo "-----------------------------------------------\n";

$reservation = Reservation::latest()->first(['id', 'start_time', 'end_time', 'hours']);

if ($reservation) {
    echo "Reservation ID: {$reservation->id}\n";
    echo "Hours: {$reservation->hours}\n";
    
    // Raw datetime objects
    echo "\nDatetime Objects:\n";
    echo "  start_time object: " . $reservation->start_time->toDateTimeString() . " ({$reservation->start_time->timezoneName})\n";
    echo "  end_time object:   " . $reservation->end_time->toDateTimeString() . " ({$reservation->end_time->timezoneName})\n";
    
    // JSON serialization (what frontend receives)
    echo "\nJSON Serialization (what frontend gets):\n";
    echo json_encode([
        'start_time' => $reservation->start_time,
        'end_time' => $reservation->end_time,
    ], JSON_PRETTY_PRINT) . "\n";
    
    // Calculate what JavaScript Date would display with Asia/Manila timezone
    echo "\nExpected Frontend Display (with timeZone: 'Asia/Manila'):\n";
    $jsStart = $reservation->start_time->copy()->setTimezone('Asia/Manila');
    $jsEnd = $reservation->end_time->copy()->setTimezone('Asia/Manila');
    echo "  Start: " . $jsStart->format('M d, Y, h:i A') . "\n";
    echo "  End:   " . $jsEnd->format('M d, Y, h:i A') . "\n";
} else {
    echo "No reservations found in database.\n";
}

echo "\n=== TEST COMPLETE ===\n";
