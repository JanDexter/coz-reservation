<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Reservation;
use App\Models\TransactionLog;
use App\Models\SpaceType;
use App\Models\Refund;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublicReservationController extends Controller
{
    /**
     * Auto-adjust start time if it's in the past.
     * Returns the adjusted start time.
     */
    protected function adjustStartTimeIfNeeded(Carbon $startTime): Carbon
    {
        $now = Carbon::now(config('app.timezone'));
        
        // If start time is in the past, adjust it to current time
        if ($startTime->lt($now)) {
            return $now;
        }
        
        return $startTime;
    }

    public function checkAvailability(Request $request)
    {
        $validated = $request->validate([
            'start_time' => 'required|date',
            'hours' => 'required|integer|min:1|max:12',
            'pax' => 'nullable|integer|min:1|max:20',
        ]);

        $startTime = Carbon::parse($validated['start_time'], config('app.timezone'));
        
        // Auto-adjust start time if it's in the past
        $startTime = $this->adjustStartTimeIfNeeded($startTime);
        $endTime = (clone $startTime)->addHours($validated['hours']);
        $requestedPax = $validated['pax'] ?? 1;
        $requestedHours = $validated['hours'];

        $spaceTypes = SpaceType::all();
        $availability = [];

        foreach ($spaceTypes as $spaceType) {
            $availableCapacity = $spaceType->getAvailableCapacity($startTime, $endTime);
            $canAccommodate = $availableCapacity >= $requestedPax;
            
            $spaceData = [
                'id' => $spaceType->id,
                'name' => $spaceType->name,
                'total_slots' => $spaceType->total_slots,
                'available_capacity' => $availableCapacity,
                'requested_pax' => $requestedPax,
                'can_accommodate' => $canAccommodate,
                'is_available' => $canAccommodate,
            ];

            // For conference rooms, provide alternative available time slots
            if (stripos($spaceType->name, 'conference') !== false && !$canAccommodate) {
                $spaceData['available_slots'] = $this->getAvailableTimeSlots(
                    $spaceType, 
                    $startTime, 
                    $requestedHours, 
                    $requestedPax
                );
            }
            
            $availability[] = $spaceData;
        }

        return response()->json([
            'availability' => $availability,
            'start_time' => $startTime->toIso8601String(),
            'end_time' => $endTime->toIso8601String(),
        ]);
    }

    /**
     * Get available time slots for conference rooms from 9am to 12am
     */
    private function getAvailableTimeSlots(SpaceType $spaceType, Carbon $requestedStart, int $hours, int $pax)
    {
        $date = $requestedStart->copy()->setTime(0, 0, 0);
        $dayStart = $date->copy()->setTime(9, 0, 0); // 9 AM
        $dayEnd = $date->copy()->setTime(23, 59, 59); // 12 AM (end of day)
        
        $availableSlots = [];
        $slotStart = $dayStart->copy();

        // Check every hour from 9am to 12am
        while ($slotStart->lessThan($dayEnd)) {
            $slotEnd = $slotStart->copy()->addHours($hours);
            
            // Make sure slot doesn't extend past midnight
            if ($slotEnd->greaterThan($dayEnd)) {
                break;
            }

            // Check if this slot is available
            $capacity = $spaceType->getAvailableCapacity($slotStart, $slotEnd);
            if ($capacity >= $pax) {
                $availableSlots[] = [
                    'start_time' => $slotStart->format('H:i'),
                    'end_time' => $slotEnd->format('H:i'),
                    'available_capacity' => $capacity,
                ];
            }

            $slotStart->addHour();
        }

        return $availableSlots;
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'space_type_id' => 'required|exists:space_types,id',
            'payment_method' => 'required|in:gcash,maya,cash',
            'hours' => 'nullable|integer|min:1|max:12',
            'pax' => 'nullable|integer|min:1|max:20',
            'notes' => 'nullable|string',
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'required|string|max:20',
            'customer_company_name' => 'nullable|string|max:255',
            'start_time' => 'nullable|date',
        ]);

        $spaceType = SpaceType::findOrFail($validated['space_type_id']);

        $hours = $validated['payment_method'] === 'cash'
            ? 1
            : ($validated['hours'] ?? 1);

        $pax = $validated['pax'] ?? 1;

        $startTime = isset($validated['start_time']) 
            ? Carbon::parse($validated['start_time'], config('app.timezone')) 
            : Carbon::now(config('app.timezone'));
        
        // Auto-adjust start time if it's in the past (e.g., missed payment deadline)
        $startTime = $this->adjustStartTimeIfNeeded($startTime);
        $endTime = (clone $startTime)->addHours($hours);

    // Check available capacity using the same logic as the availability check endpoint
    $availableCapacity = $spaceType->getAvailableCapacity($startTime, $endTime);

        // Check if there's enough capacity for this booking
        if ($availableCapacity < $pax) {
            $message = $availableCapacity > 0
                ? "Only {$availableCapacity} slot(s) available for this space at the selected time. You're trying to book for {$pax} person(s)."
                : 'This space is fully booked for the requested time. Please try a different time or choose another space.';
            
            return redirect()
                ->back()
                ->withErrors([
                    'space_type_id' => $message,
                ])
                ->withInput();
        }

        $appliedHourlyRate = $spaceType->hourly_rate ?? $spaceType->default_price ?? 0;
        $appliedDiscountHours = $spaceType->default_discount_hours;
        $appliedDiscountPercentage = $spaceType->default_discount_percentage;
        $isDiscounted = $appliedDiscountHours && $hours >= $appliedDiscountHours;

        $baseCost = $hours * $appliedHourlyRate;
        if ($isDiscounted && $appliedDiscountPercentage) {
            $baseCost -= ($baseCost * ($appliedDiscountPercentage / 100));
        }

        // Find or create customer first to validate cash bookings
        $customer = Customer::firstOrCreate(
            ['email' => $validated['customer_email']],
            [
                'name' => $validated['customer_name'],
                'phone' => $validated['customer_phone'],
                'company_name' => $validated['customer_company_name'] ?? null,
                'contact_person' => $validated['customer_name'],
                'space_type_id' => $spaceType->id,
                'user_id' => Auth::id(),
            ]
        );

        // If the customer already existed, update their contact info
        if (!$customer->wasRecentlyCreated) {
            $customer->fill([
                'name' => $validated['customer_name'],
                'phone' => $validated['customer_phone'],
                'space_type_id' => $spaceType->id,
            ]);

            if (!empty($validated['customer_company_name'])) {
                $customer->company_name = $validated['customer_company_name'];
            }
            $customer->save();
        }

        // VALIDATION: Check if customer can make cash booking
        if ($validated['payment_method'] === 'cash') {
            $validation = $customer->validateCashBooking();
            
            if (!$validation['valid']) {
                return redirect()
                    ->back()
                    ->withErrors([
                        'payment_method' => $validation['message'],
                    ])
                    ->withInput();
            }
        }

        // VALIDATION: Check for overlapping reservations
        $hasOverlappingReservation = $customer->reservations()
            ->whereIn('status', ['pending', 'on_hold', 'confirmed', 'active', 'paid'])
            ->where(function($q) use ($startTime, $endTime) {
                $q->where(function($query) use ($startTime, $endTime) {
                    // New reservation starts during an existing reservation
                    $query->where('start_time', '<=', $startTime)
                          ->where('end_time', '>', $startTime);
                })->orWhere(function($query) use ($startTime, $endTime) {
                    // New reservation ends during an existing reservation
                    $query->where('start_time', '<', $endTime)
                          ->where('end_time', '>=', $endTime);
                })->orWhere(function($query) use ($startTime, $endTime) {
                    // New reservation completely contains an existing reservation
                    $query->where('start_time', '>=', $startTime)
                          ->where('end_time', '<=', $endTime);
                });
            })
            ->exists();

        if ($hasOverlappingReservation) {
            return redirect()
                ->back()
                ->withErrors([
                    'start_time' => 'You already have a reservation during this time period. Please choose a different time or cancel your existing reservation first.',
                ])
                ->withInput();
        }

    $reservation = DB::transaction(function () use ($validated, $spaceType, $hours, $pax, $appliedHourlyRate, $appliedDiscountHours, $appliedDiscountPercentage, $isDiscounted, $baseCost, $startTime, $endTime, $customer) {
            // Update additional customer info if needed
            if (empty($customer->contact_person)) {
                $customer->contact_person = $validated['customer_name'];
            }

            if (Auth::id() && !$customer->user_id) {
                $customer->user_id = Auth::id();
            }

            $customer->save();

            // Auto-assign to an available physical space without time conflicts
            $assignedSpace = \App\Models\Space::where('space_type_id', $spaceType->id)
                ->whereDoesntHave('reservations', function($q) use ($startTime, $endTime) {
                    // Check for overlapping reservations
                    $q->active()
                      ->where('start_time', '<', $endTime)
                      ->where('end_time', '>', $startTime);
                })
                ->first();

            $spaceId = $assignedSpace ? $assignedSpace->id : null;

            // Create the reservation
            $reservation = Reservation::create([
                'user_id' => Auth::id(), // Can be null if not logged in
                'customer_id' => $customer->id,
                'space_id' => $spaceId, // Assign to physical space if available
                'space_type_id' => $spaceType->id,
                'payment_method' => $validated['payment_method'],
                'hours' => $hours,
                'pax' => $pax,
                'status' => $validated['payment_method'] === 'cash' ? 'on_hold' : 'paid',
                'hold_until' => $validated['payment_method'] === 'cash' ? Carbon::now()->addHour() : null,
                'notes' => $validated['notes'] ?? null,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'applied_hourly_rate' => $appliedHourlyRate,
                'applied_discount_hours' => $appliedDiscountHours,
                'applied_discount_percentage' => $appliedDiscountPercentage,
                'is_discounted' => $isDiscounted,
                'cost' => round($baseCost, 2),
            ]);

            // If payment is made online (gcash/maya), create a transaction log for the full amount
            if (in_array($validated['payment_method'], ['gcash', 'maya'])) {
                $reservation->amount_paid = $reservation->total_cost;
                $reservation->save();
                
                TransactionLog::create([
                    'type' => 'payment',
                    'reservation_id' => $reservation->id,
                    'customer_id' => $customer->id,
                    'processed_by' => Auth::id(),
                    'amount' => $reservation->total_cost,
                    'payment_method' => $validated['payment_method'],
                    'status' => 'completed',
                    'reference_number' => TransactionLog::generateReferenceNumber('payment'),
                    'description' => "Online payment for reservation #{$reservation->id} via " . strtoupper($validated['payment_method']),
                    'notes' => 'Customer self-service booking payment',
                ]);
            }

            return $reservation;
        });

        // Load the spaceType relationship to avoid N+1 and ensure space_type data is available
        $reservation->load(['spaceType', 'space']);

        return redirect()
            ->route('customer.view')
            ->with('reservationCreated', [
                'id' => $reservation->id,
                'status' => $reservation->status,
                'total_cost' => $reservation->total_cost,
                'space_type_name' => $reservation->spaceType->name ?? null,
                'space_name' => optional($reservation->space)->name ?? $reservation->spaceType->name ?? null,
            ]);
    }

    public function extend(Request $request, Reservation $reservation)
    {
        // Ensure the user owns this reservation
        if ($reservation->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'hours' => 'required|integer|min:1|max:12',
        ]);

        $extensionHours = $validated['hours'];
        $hourlyRate = $reservation->effective_hourly_rate;
        $extensionCost = $extensionHours * $hourlyRate;

        DB::transaction(function () use ($reservation, $extensionHours, $extensionCost) {
            // Extend the end time
            $reservation->end_time = Carbon::parse($reservation->end_time)->addHours($extensionHours);
            $reservation->hours += $extensionHours;
            
            // Update the cost
            $currentCost = $reservation->cost ?? $reservation->total_cost;
            $reservation->cost = round($currentCost + $extensionCost, 2);
            
            // If it was fully paid, mark as partial since they owe more now
            if ($reservation->status === 'paid' || $reservation->status === 'completed') {
                $reservation->status = 'partial';
            }
            
            $reservation->save();
        });

        return redirect()
            ->back()
            ->with('success', "Reservation extended by {$extensionHours} hour(s). Additional cost: " . number_format($extensionCost, 2));
    }

    public function endEarly(Request $request, Reservation $reservation)
    {
        // Ensure the user owns this reservation
        if ($reservation->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        // Only allow ending active reservations early
        if ($reservation->status !== 'active') {
            return redirect()
                ->back()
                ->with('error', 'Only active reservations can be ended early.');
        }

        $now = Carbon::now();
        $originalEnd = Carbon::parse($reservation->end_time);
        
        // Check if already past end time
        if ($now->gte($originalEnd)) {
            return redirect()
                ->back()
                ->with('error', 'Reservation has already ended.');
        }

        DB::transaction(function () use ($reservation, $now) {
            $originalStart = Carbon::parse($reservation->start_time);
            $originalEnd = Carbon::parse($reservation->end_time);
            $actualHoursUsed = $now->diffInMinutes($originalStart) / 60;
            
            // Calculate refund (if any)
            $hourlyRate = $reservation->effective_hourly_rate;
            $originalCost = $reservation->total_cost;
            $actualCost = ceil($actualHoursUsed) * $hourlyRate;
            $refundAmount = max(0, $originalCost - $actualCost);
            
            // Update reservation
            $reservation->end_time = $now;
            $reservation->hours = ceil($actualHoursUsed);
            $reservation->cost = round($actualCost, 2);
            $reservation->status = 'completed';
            $reservation->notes = ($reservation->notes ?? '') . "\nEnded early. Refund: ₱" . number_format($refundAmount, 2);
            $reservation->save();
            
            // Adjust amount_paid if there was overpayment
            if ($reservation->amount_paid > $actualCost) {
                $reservation->amount_paid = $actualCost;
                $reservation->save();
            }
        });

        return redirect()
            ->back()
            ->with('success', 'Reservation ended early. Any applicable refund will be processed.');
    }

    public function destroy(Reservation $reservation)
    {
        // Ensure the user owns this reservation
        if ($reservation->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        // Only allow canceling pending, on_hold, or confirmed reservations
        if (!in_array($reservation->status, ['pending', 'on_hold', 'confirmed', 'paid', 'active'])) {
            return redirect()
                ->back()
                ->with('error', 'Only pending, on-hold, confirmed, paid, or active reservations can be cancelled.');
        }

        // Calculate refund if there was payment
        $refundInfo = Refund::calculateRefund($reservation);
        $refundAmount = $refundInfo['refund_amount'];
        $cancellationFee = $refundInfo['cancellation_fee'];

        DB::transaction(function () use ($reservation, $refundAmount, $cancellationFee, $refundInfo) {
            // Update reservation status
            $reservation->status = 'cancelled';
            $reservation->save();

            // Log the cancellation
            TransactionLog::logCancellation(
                $reservation,
                null, // Customer-initiated, no admin processor
                "Cancelled {$refundInfo['hours_until_start']} hours before start. Policy: {$refundInfo['percentage']}% refund"
            );

            // Create refund record if payment was made
            if ($reservation->amount_paid > 0) {
                $refund = Refund::create([
                    'reservation_id' => $reservation->id,
                    'customer_id' => $reservation->customer_id,
                    'refund_amount' => $refundAmount,
                    'original_amount_paid' => $reservation->amount_paid,
                    'cancellation_fee' => $cancellationFee,
                    'refund_method' => $reservation->payment_method,
                    'status' => $refundAmount > 0 ? 'pending' : 'completed', // All refunds are pending, awaiting admin approval
                    'reason' => 'Customer cancelled reservation',
                    'reference_number' => Refund::generateReferenceNumber(),
                    'notes' => sprintf(
                        'Cancelled %.1f hours before start time. Refund: %d%%',
                        $refundInfo['hours_until_start'],
                        $refundInfo['percentage']
                    ),
                ]);

                // Log the refund request (not yet processed)
                if ($refundAmount > 0) {
                    TransactionLog::logRefund(
                        $reservation,
                        $refundAmount,
                        null, // Customer-initiated
                        "Refund request pending admin approval | Refund amount: ₱" . number_format($refundAmount, 2) . " | Cancellation fee: ₱" . number_format($cancellationFee, 2)
                    );
                }
            }
        });

        // Build success message
        $message = 'Reservation cancelled successfully.';
        if ($reservation->amount_paid > 0) {
            if ($refundAmount > 0) {
                $message .= sprintf(
                    ' Your refund request of ₱%s has been submitted and is pending admin approval (Cancellation fee: ₱%s). You will be notified once processed.',
                    number_format($refundAmount, 2),
                    number_format($cancellationFee, 2)
                );
            } else {
                $message .= ' No refund available as the reservation has already started or cancellation was too late.';
            }
        }

        return redirect()
            ->back()
            ->with('success', $message);
    }
}

