<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Refund;
use App\Models\TransactionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class AdminReservationController extends Controller
{
    public function update(Request $request, Reservation $reservation)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,on_hold,confirmed,active,partial,paid,completed,cancelled',
            'payment_method' => 'nullable|in:cash,gcash,maya,card,bank',
            'amount_paid' => 'nullable|numeric|min:0',
            'start_time' => 'nullable|date',
            'end_time' => 'nullable|date|after_or_equal:start_time',
            'hours' => 'nullable|numeric|min:0',
            'pax' => 'nullable|integer|min:1',
            'notes' => 'nullable|string|max:1000',
            'remove_discount' => 'nullable|boolean',
        ]);

        $reservation->loadMissing('spaceType');

        $newStart = array_key_exists('start_time', $validated) && $validated['start_time']
            ? Carbon::parse($validated['start_time'])
            : $reservation->start_time;

        $newEnd = array_key_exists('end_time', $validated) && $validated['end_time']
            ? Carbon::parse($validated['end_time'])
            : $reservation->end_time;

        if ($newStart) {
            if ($reservation->space_id) {
                // For specific space assignments, check for any overlapping reservation
                $hasConflict = Reservation::query()
                    ->active()
                    ->where('id', '!=', $reservation->id)
                    ->where('space_id', $reservation->space_id)
                    ->overlapping($newStart, $newEnd)
                    ->exists();

                if ($hasConflict) {
                    return back()
                        ->withErrors([
                            'start_time' => 'Another reservation already occupies this space during the selected time window.',
                        ])
                        ->withInput();
                }
            } elseif ($reservation->space_type_id) {
                // For space type reservations, check available capacity considering pax and physical space count
                $spaceType = $reservation->spaceType;
                $newPax = (int) ($validated['pax'] ?? $reservation->pax ?? 1);

                if ($spaceType) {
                    $availableCapacity = $spaceType->getAvailableCapacity($newStart, $newEnd, $reservation->id);

                    if ($availableCapacity < $newPax) {
                        $message = $availableCapacity > 0
                            ? "Only {$availableCapacity} slot(s) available for this space at the selected time. You're trying to book for {$newPax} person(s)."
                            : 'This space type is fully booked for the selected time. Please adjust the schedule to continue.';

                        return back()
                            ->withErrors([
                                'start_time' => $message,
                            ])
                            ->withInput();
                    }
                } else {
                    $hasConflict = Reservation::query()
                        ->active()
                        ->where('id', '!=', $reservation->id)
                        ->where('space_type_id', $reservation->space_type_id)
                        ->overlapping($newStart, $newEnd)
                        ->exists();

                    if ($hasConflict) {
                        return back()
                            ->withErrors([
                                'start_time' => 'This space type is fully booked for the selected time. Please adjust the schedule to continue.',
                            ])
                            ->withInput();
                    }
                }
            } else {
                // No space or space type assigned, just check for any conflicts
                $hasConflict = Reservation::query()
                    ->active()
                    ->where('id', '!=', $reservation->id)
                    ->overlapping($newStart, $newEnd)
                    ->exists();
                
                if ($hasConflict) {
                    return back()
                        ->withErrors([
                            'start_time' => 'There is already another reservation covering this schedule.',
                        ])
                        ->withInput();
                }
            }
        }

        DB::transaction(function () use ($validated, $reservation) {
            $originalAmount = (float) ($reservation->amount_paid ?? 0);
            $newAmount = array_key_exists('amount_paid', $validated)
                ? (float) $validated['amount_paid']
                : $originalAmount;

            $updates = collect($validated)->except(['amount_paid'])->all();

            if (array_key_exists('start_time', $updates) && $updates['start_time']) {
                $updates['start_time'] = Carbon::parse($updates['start_time']);
            }

            if (array_key_exists('end_time', $updates) && $updates['end_time']) {
                $updates['end_time'] = Carbon::parse($updates['end_time']);
            }

            if (array_key_exists('notes', $updates) && $updates['notes']) {
                $updates['notes'] = strip_tags($updates['notes']);
            }

            // Handle discount removal for future reservations
            if (!empty($validated['remove_discount']) && $reservation->is_discounted) {
                $updates['is_discounted'] = false;
                $updates['applied_discount_percentage'] = null;
                $updates['applied_discount_hours'] = null;
                
                // Recalculate total cost without discount
                if ($reservation->hours && $reservation->spaceType) {
                    $hourlyRate = $reservation->spaceType->hourly_rate ?? 0;
                    $updates['total_cost'] = $reservation->hours * $hourlyRate;
                }
            }

            $reservation->fill($updates);

            if (array_key_exists('amount_paid', $validated)) {
                $reservation->amount_paid = $newAmount;
                
                // Create transaction log if payment amount changed
                if ($newAmount != $originalAmount) {
                    $paymentDiff = $newAmount - $originalAmount;
                    
                    if ($paymentDiff > 0) {
                        // Payment added
                        TransactionLog::create([
                            'type' => 'payment',
                            'reservation_id' => $reservation->id,
                            'customer_id' => $reservation->customer_id,
                            'processed_by' => auth()->id(),
                            'amount' => $paymentDiff,
                            'payment_method' => $validated['payment_method'] ?? $reservation->payment_method ?? 'cash',
                            'status' => $validated['status'] ?? $reservation->status,
                            'reference_number' => TransactionLog::generateReferenceNumber('payment'),
                            'description' => "Payment adjustment: ₱" . number_format($paymentDiff, 2) . " added for reservation #{$reservation->id}",
                            'notes' => $validated['notes'] ?? null,
                        ]);
                    } elseif ($paymentDiff < 0) {
                        // Payment reduced (refund)
                        TransactionLog::create([
                            'type' => 'refund',
                            'reservation_id' => $reservation->id,
                            'customer_id' => $reservation->customer_id,
                            'processed_by' => auth()->id(),
                            'amount' => $paymentDiff, // Negative amount
                            'payment_method' => $validated['payment_method'] ?? $reservation->payment_method ?? 'cash',
                            'status' => 'approved',
                            'reference_number' => TransactionLog::generateReferenceNumber('refund'),
                            'description' => "Payment adjustment: ₱" . number_format(abs($paymentDiff), 2) . " refunded for reservation #{$reservation->id}",
                            'notes' => $validated['notes'] ?? null,
                        ]);
                    }
                }
            }

            $reservation->save();

            if ($reservation->customer && array_key_exists('amount_paid', $validated)) {
                $customer = $reservation->customer;
                $customer->amount_paid = max(0, ($customer->amount_paid ?? 0) + ($newAmount - $originalAmount));
                $customer->save();
            }
        });

        return back()->with('success', 'Reservation updated successfully.');
    }

    public function close(Request $request, Reservation $reservation)
    {
        DB::transaction(function () use ($reservation) {
            $reservation->update([
                'status' => 'completed',
                'end_time' => $reservation->end_time ?? Carbon::now(),
            ]);
        });

        return back()->with('success', 'Reservation closed successfully.');
    }

    public function cancel(Request $request, Reservation $reservation)
    {
        // Only allow canceling non-completed/non-cancelled reservations
        if (in_array($reservation->status, ['completed', 'cancelled'])) {
            return redirect()
                ->back()
                ->with('error', 'This reservation has already been ' . $reservation->status . '.');
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        // Calculate refund if there was payment
        $refundInfo = Refund::calculateRefund($reservation);
        $refundAmount = $refundInfo['refund_amount'];
        $cancellationFee = $refundInfo['cancellation_fee'];

        DB::transaction(function () use ($reservation, $refundAmount, $cancellationFee, $refundInfo, $validated) {
            // Update reservation status
            $reservation->status = 'cancelled';
            $reservation->save();

            $adminNotes = sprintf(
                'Admin cancelled %s hours before start. Policy: %s%% refund. Reason: %s',
                number_format($refundInfo['hours_until_start'], 1),
                $refundInfo['percentage'],
                $validated['reason'] ?? 'No reason provided'
            );

            // Log the cancellation
            TransactionLog::logCancellation(
                $reservation,
                Auth::id(),
                $adminNotes
            );

            // Create refund record if payment was made
            if ($reservation->amount_paid > 0) {
                $refund = Refund::create([
                    'reservation_id' => $reservation->id,
                    'customer_id' => $reservation->customer_id,
                    'processed_by' => Auth::id(),
                    'refund_amount' => $refundAmount,
                    'original_amount_paid' => $reservation->amount_paid,
                    'cancellation_fee' => $cancellationFee,
                    'refund_method' => $reservation->payment_method,
                    'status' => $refundAmount > 0 ? 'pending' : 'completed', // Admin cancellations also require approval
                    'reason' => $validated['reason'] ?? 'Admin cancelled reservation',
                    'reference_number' => Refund::generateReferenceNumber(),
                    'notes' => sprintf(
                        'Admin cancelled %.1f hours before start time. Refund: %d%%',
                        $refundInfo['hours_until_start'],
                        $refundInfo['percentage']
                    ),
                ]);

                // Log the refund request (pending approval)
                if ($refundAmount > 0) {
                    TransactionLog::logRefund(
                        $reservation,
                        $refundAmount,
                        Auth::id(),
                        "Admin cancellation - Refund request pending approval | Amount: ₱" . number_format($refundAmount, 2) . " | Fee: ₱" . number_format($cancellationFee, 2)
                    );
                }
            }
        });

        // Build success message
        $message = 'Reservation cancelled successfully.';
        if ($reservation->amount_paid > 0) {
            if ($refundAmount > 0) {
                $message .= sprintf(
                    ' A refund of ₱%s will be processed (Cancellation fee: ₱%s).',
                    number_format($refundAmount, 2),
                    number_format($cancellationFee, 2)
                );
            } else {
                $message .= ' No refund issued as cancellation was after start time or policy applies 0% refund.';
            }
        }

        return redirect()
            ->back()
            ->with('success', $message);
    }
}
