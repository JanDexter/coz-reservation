<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\SpaceType;
use App\Models\TransactionLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CustomerViewController extends Controller
{
    public function __invoke()
    {
        $user = Auth::user();
        
        // If user is logged in but hasn't verified email, log them out and redirect
        if ($user && !$user->hasVerifiedEmail() && $user->isCustomer()) {
            Auth::logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();
            return redirect()->route('login')->with('status', 'Please verify your email address to continue.');
        }
        
        $reservations = [];

        if ($user) {
            // Only show active and upcoming reservations (not completed or past)
            $reservations = Reservation::with(['space.spaceType', 'spaceType', 'customer'])
                ->where(function ($q) use ($user) {
                    // Include reservations created by this user
                    $q->where('user_id', $user->id);
                    // Also include reservations tied to the customer's profile (e.g., admin-started open time)
                    if ($user->relationLoaded('customer') ? $user->customer : $user->customer()->exists()) {
                        $customerId = optional($user->customer)->id;
                        if ($customerId) {
                            $q->orWhere('customer_id', $customerId);
                        }
                    }
                })
                // Filter to only show active/upcoming reservations
                ->whereIn('status', ['pending', 'on_hold', 'confirmed', 'active'])
                ->latest()
                ->get()
                ->map(function ($reservation) {
                    $spaceTypeName = optional(optional($reservation->space)->spaceType)->name
                        ?? optional($reservation->spaceType)->name
                        ?? 'Reserved Space';

                    return [
                        'id' => $reservation->id,
                        'service' => $spaceTypeName,
                        'date' => $reservation->created_at->toFormattedDateString(),
                        'start_time' => $reservation->start_time,
                        'end_time' => $reservation->end_time,
                        'hours' => $reservation->hours,
                        'pax' => $reservation->pax,
                        'payment_method' => $reservation->payment_method,
                        'total_cost' => $reservation->total_cost,
                        'amount_paid' => $reservation->amount_paid ?? 0,
                        'amount_remaining' => $reservation->amount_remaining,
                        'is_partially_paid' => $reservation->is_partially_paid,
                        'is_fully_paid' => $reservation->is_fully_paid,
                        'effective_hourly_rate' => $reservation->effective_hourly_rate,
                        'status' => $reservation->status,
                        'is_open_time' => (bool) $reservation->is_open_time,
                        'space_type_id' => $reservation->space_type_id,
                        'space_type' => $reservation->spaceType ? [
                            'id' => $reservation->spaceType->id,
                            'name' => $reservation->spaceType->name,
                        ] : null,
                        'customer' => $reservation->customer ? [
                            'name' => $reservation->customer->name,
                            'email' => $reservation->customer->email,
                            'phone' => $reservation->customer->phone,
                            'company_name' => $reservation->customer->company_name,
                        ] : null,
                        'customer_name' => $reservation->customer->name ?? null,
                        'customer_email' => $reservation->customer->email ?? null,
                        'customer_phone' => $reservation->customer->phone ?? null,
                        'customer_company_name' => $reservation->customer->company_name ?? null,
                    ];
                });
        }

        $spaceTypes = SpaceType::select(
                'id',
                'name',
                'description',
        'photo_path',
                'default_price',
                'hourly_rate',
                'pricing_type',
                'default_discount_hours',
                'default_discount_percentage',
                'available_slots',
                'total_slots'
            )
            ->orderBy('name')
            ->get()
            ->map(function (SpaceType $type) {
                $price = $type->hourly_rate ?? $type->default_price;

                return [
                    'id' => $type->id,
                    'name' => $type->name,
                    'slug' => Str::slug($type->name),
                    'description' => $type->description,
                    'photo_url' => $type->photo_path ? asset('storage/' . $type->photo_path) . '?v=' . urlencode(optional($type->updated_at)->timestamp ?? time()) : null,
                    'price_per_hour' => $price,
                    'pricing_type' => $type->pricing_type ?? 'per_person',
                    'discount_hours' => $type->default_discount_hours,
                    'discount_percentage' => $type->default_discount_percentage,
                    'available_slots' => $type->available_slots,
                    'total_slots' => $type->total_slots,
                ];
            });

        return Inertia::render('CustomerView/Index', [
            'spaceTypes' => $spaceTypes,
            'auth' => [
                'user' => Auth::user(),
            ],
            'reservations' => $reservations,
        ]);
    }

    public function transactions()
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['transactions' => []]);
        }

        // Get customer ID if exists
        $customerId = $user->relationLoaded('customer') 
            ? optional($user->customer)->id 
            : optional($user->customer()->first())->id;

        // Fetch transaction logs for this customer
        $transactions = TransactionLog::with(['reservation.spaceType', 'processedBy'])
            ->when($customerId, function ($query) use ($customerId) {
                $query->where('customer_id', $customerId);
            })
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'type' => $log->type,
                    'amount' => $log->amount,
                    'payment_method' => $log->payment_method,
                    'status' => $log->status,
                    'reference_number' => $log->reference_number,
                    'description' => $log->description,
                    'notes' => $log->notes,
                    'created_at' => $log->created_at,
                    'reservation' => $log->reservation ? [
                        'id' => $log->reservation->id,
                        'start_time' => $log->reservation->start_time,
                        'end_time' => $log->reservation->end_time,
                        'space_type' => $log->reservation->spaceType ? [
                            'name' => $log->reservation->spaceType->name,
                        ] : null,
                    ] : null,
                    'processed_by' => $log->processedBy ? [
                        'name' => $log->processedBy->name,
                    ] : null,
                ];
            });

        return response()->json(['transactions' => $transactions]);
    }

    /**
     * Get reservation history (completed and cancelled reservations)
     */
    public function reservationHistory()
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['history' => []]);
        }

        // Get all completed, cancelled, and past paid reservations
        $history = Reservation::with(['space.spaceType', 'spaceType', 'customer'])
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id);
                if ($user->relationLoaded('customer') ? $user->customer : $user->customer()->exists()) {
                    $customerId = optional($user->customer)->id;
                    if ($customerId) {
                        $q->orWhere('customer_id', $customerId);
                    }
                }
            })
            ->where(function($q) {
                // Include completed, cancelled, and paid reservations
                $q->whereIn('status', ['completed', 'cancelled', 'paid'])
                  // Also include any past reservations regardless of status
                  ->orWhere('end_time', '<', now());
            })
            ->orderByDesc('start_time')
            ->get()
            ->map(function ($reservation) {
                $spaceTypeName = optional(optional($reservation->space)->spaceType)->name
                    ?? optional($reservation->spaceType)->name
                    ?? 'Reserved Space';

                return [
                    'id' => $reservation->id,
                    'service' => $spaceTypeName,
                    'date' => $reservation->created_at->toFormattedDateString(),
                    'start_time' => $reservation->start_time,
                    'end_time' => $reservation->end_time,
                    'hours' => $reservation->hours,
                    'pax' => $reservation->pax,
                    'payment_method' => $reservation->payment_method,
                    'total_cost' => $reservation->total_cost,
                    'amount_paid' => $reservation->amount_paid ?? 0,
                    'amount_remaining' => $reservation->amount_remaining,
                    'is_partially_paid' => $reservation->is_partially_paid,
                    'is_fully_paid' => $reservation->is_fully_paid,
                    'effective_hourly_rate' => $reservation->effective_hourly_rate,
                    'status' => $reservation->status,
                    'is_open_time' => (bool) $reservation->is_open_time,
                    'space_type_id' => $reservation->space_type_id,
                    'space_type' => $reservation->spaceType ? [
                        'id' => $reservation->spaceType->id,
                        'name' => $reservation->spaceType->name,
                    ] : null,
                    'customer' => $reservation->customer ? [
                        'name' => $reservation->customer->name,
                        'email' => $reservation->customer->email,
                        'phone' => $reservation->customer->phone,
                        'company_name' => $reservation->customer->company_name,
                    ] : null,
                    'customer_name' => $reservation->customer->name ?? null,
                    'customer_email' => $reservation->customer->email ?? null,
                    'customer_phone' => $reservation->customer->phone ?? null,
                    'customer_company_name' => $reservation->customer->company_name ?? null,
                ];
            });

        return response()->json(['history' => $history]);
    }
}

