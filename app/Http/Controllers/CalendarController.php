<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Space;
use App\Models\SpaceType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CalendarController extends Controller
{
    public function index(Request $request)
    {
        $reservations = Reservation::with(['customer', 'space.spaceType', 'spaceType'])
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->get();

        $events = $reservations->map(function ($reservation) {
            $customer = $reservation->customer;
            $space = $reservation->space;
            $spaceType = $reservation->spaceType ?: ($space ? $space->spaceType : null);

            $totalCost = $reservation->total_cost;
            $amountPaid = (float) ($reservation->amount_paid ?? 0);
            $amountRemaining = $reservation->amount_remaining;

            return [
                'id' => $reservation->id,
                'title' => $space?->name ?? $spaceType?->name ?? 'Reservation',
                'start' => optional($reservation->start_time)->toIso8601String(),
                'end' => optional($reservation->end_time)->toIso8601String(),
                'allDay' => (bool) $reservation->is_open_time,
                'extendedProps' => [
                    'space' => $space?->name,
                    'spaceType' => $spaceType?->name,
                    'spaceTypeId' => $spaceType?->id,
                    'customer' => $customer?->name ?? $customer?->company_name,
                    'contact' => $customer?->contact_person,
                    'email' => $customer?->email,
                    'phone' => $customer?->phone,
                    'status' => $reservation->status,
                    'paymentMethod' => $reservation->payment_method,
                    'rate' => $reservation->effective_hourly_rate,
                    'cost' => $totalCost,
                    'amountPaid' => $amountPaid,
                    'amountRemaining' => $amountRemaining,
                    'is_open_time' => (bool) $reservation->is_open_time,
                    'notes' => $reservation->notes,
                    'hours' => $reservation->hours,
                    'pax' => $reservation->pax,
                    'reservation' => [
                        'id' => $reservation->id,
                        'status' => $reservation->status,
                        'payment_method' => $reservation->payment_method,
                        'amount_paid' => $amountPaid,
                        'amount_remaining' => $amountRemaining,
                        'total_cost' => $totalCost,
                        'notes' => $reservation->notes,
                        'start_time' => optional($reservation->start_time)->toIso8601String(),
                        'end_time' => optional($reservation->end_time)->toIso8601String(),
                        'hours' => $reservation->hours,
                        'pax' => $reservation->pax,
                        'is_open_time' => (bool) $reservation->is_open_time,
                        'space_type' => $spaceType ? [
                            'id' => $spaceType->id,
                            'name' => $spaceType->name,
                        ] : null,
                        'space' => $space ? [
                            'id' => $space->id,
                            'name' => $space->name,
                        ] : null,
                        'customer' => $customer ? [
                            'id' => $customer->id,
                            'name' => $customer->name ?? $customer->company_name,
                            'email' => $customer->email,
                            'phone' => $customer->phone,
                        ] : null,
                    ],
                ],
                'backgroundColor' => $this->getEventColor($reservation->status),
                'borderColor' => $this->getEventColor($reservation->status),
            ];
        })->values();

        $spaceTypes = SpaceType::all();
        $spaces = Space::all();

        return Inertia::render('Calendar/Index', [
            'events' => $events,
            'spaceTypes' => $spaceTypes,
            'spaces' => $spaces,
        ]);
    }

    private function getEventColor($status)
    {
        switch ($status) {
            case 'paid':
            case 'completed':
                return '#10B981'; // Emerald 500
            case 'active':
            case 'confirmed':
                return '#3B82F6'; // Blue 500
            case 'on_hold':
            case 'pending':
                return '#F59E0B'; // Amber 500
            case 'cancelled':
                return '#EF4444'; // Red 500
            default:
                return '#6B7280'; // Gray 500
        }
    }
}
