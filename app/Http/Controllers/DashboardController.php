<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\User;
use App\Models\SpaceType;
use App\Models\Space;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $stats = [
            'total_customers' => Customer::count(),
            'active_customers' => Customer::where('status', 'active')->count(),
            'inactive_customers' => Customer::where('status', 'inactive')->count(),
            'customer_users' => User::whereHas('customer')->count(),
            'total_users' => User::count(),
        ];

        // Get space types with occupancy information based on active reservations
        $now = now();
        $spaceTypes = SpaceType::with([
            'spaces' => function($query) use ($now) {
                $query->with([
                    'currentCustomer',
                    'reservations' => function($q) use ($now) {
                        // Get currently active reservations (started but not ended)
                        $q->where('start_time', '<=', $now)
                          ->where(function($sub) use ($now) {
                              $sub->whereNull('end_time')
                                  ->orWhere('end_time', '>', $now);
                          })
                          ->whereNotIn('status', ['completed', 'cancelled'])
                          ->with('customer')
                          ->orderBy('start_time', 'desc');
                    }
                ]);
            }
        ])->get();
        
        // Add dynamic occupation status to each space based on active reservations
        $spaceTypes->each(function($spaceType) use ($now) {
            $spaceType->spaces->each(function($space) use ($now) {
                $activeReservation = $space->reservations->first();
                
                if ($activeReservation) {
                    $space->is_currently_occupied = true;
                    $space->current_occupation = [
                        'customer_name' => $activeReservation->customer->display_name ?? $activeReservation->customer->name ?? $activeReservation->customer->company_name,
                        'start_time' => $activeReservation->start_time,
                        'end_time' => $activeReservation->end_time,
                        'reservation_id' => $activeReservation->id,
                        'status' => $activeReservation->status,
                    ];
                } else {
                    $space->is_currently_occupied = false;
                    $space->current_occupation = null;
                }
                
                // Unset the reservations collection to keep response size manageable
                unset($space->reservations);
            });
        });

        // Get recent transactions (completed reservations)
        $recentTransactions = Reservation::with(['customer', 'space.spaceType'])
            ->whereIn('status', ['completed', 'paid'])
            ->whereNotNull('end_time')
            ->orderBy('end_time', 'desc')
            ->limit(10)
            ->get()
            ->map(function($reservation) {
                // For open-time, always use the saved cost (should be set on end)
                $cost = $reservation->is_open_time ? $reservation->cost : ($reservation->cost ?? $reservation->total_cost);
                return [
                    'id' => $reservation->id,
                    'customer_name' => $reservation->customer->company_name ?? $reservation->customer->name ?? 'N/A',
                    'customer_email' => $reservation->customer->email ?? null,
                    'customer_phone' => $reservation->customer->phone ?? null,
                    'space_name' => $reservation->space->name ?? 'N/A',
                    'space_type' => $reservation->space->spaceType->name ?? 'N/A',
                    'start_time' => $reservation->start_time,
                    'end_time' => $reservation->end_time,
                    'cost' => $cost,
                    'total_cost' => $reservation->total_cost,
                    'status' => $reservation->status,
                    'payment_method' => $reservation->payment_method,
                    'amount_paid' => $reservation->amount_paid,
                    'amount_remaining' => $reservation->amount_remaining,
                    'hours' => $reservation->hours,
                    'pax' => $reservation->pax,
                    'is_open_time' => $reservation->is_open_time,
                    'notes' => $reservation->notes,
                ];
            });

        // Get active services (currently in-use reservations from calendar)
        // A service is "active" if:
        // 1. It has started (start_time <= now)
        // 2. It hasn't ended yet (end_time is null OR end_time > now)
        // 3. Status is not completed or cancelled
        $activeServices = Reservation::with(['customer', 'space.spaceType'])
            ->where('start_time', '<=', now())
            ->where(function($query) {
                $query->whereNull('end_time')
                      ->orWhere('end_time', '>', now());
            })
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->orderBy('start_time', 'desc')
            ->get()
            ->map(function($reservation) {
                return [
                    'id' => $reservation->id,
                    'customer_name' => $reservation->customer->company_name ?? $reservation->customer->name ?? 'N/A',
                    'space_name' => $reservation->space->name ?? 'N/A',
                    'space_type' => $reservation->space->spaceType->name ?? 'N/A',
                    'start_time' => $reservation->start_time,
                    'end_time' => $reservation->end_time,
                    'status' => $reservation->status,
                    'hourly_rate' => $reservation->effective_hourly_rate,
                    'is_open_time' => $reservation->is_open_time,
                ];
            });

        // Sorting params
        $sortBy = $request->query('sort_by', 'date');
        $sortDir = strtolower($request->query('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = Customer::query()
            ->with(['user', 'assignedSpace.spaceType'])
            ->leftJoin('spaces', 'spaces.current_customer_id', '=', 'customers.id')
            ->leftJoin('space_types', 'space_types.id', '=', 'spaces.space_type_id')
            ->select('customers.*')
            ->addSelect(DB::raw('COALESCE(NULLIF(customers.name, ""), NULLIF(customers.company_name, ""), NULLIF(customers.contact_person, ""), customers.email) AS sort_name'))
            ->addSelect(DB::raw('space_types.name AS assigned_space_type'));

        switch ($sortBy) {
            case 'name':
                $query->orderBy('sort_name', $sortDir);
                break;
            case 'space_type':
                $query->orderBy('assigned_space_type', $sortDir);
                break;
            case 'status':
                $query->orderBy('customers.status', $sortDir);
                break;
            case 'date':
            default:
                $query->orderBy('customers.created_at', $sortDir);
                // tie-breaker by status
                $query->orderBy('customers.status', 'asc');
                $sortBy = 'date'; // normalize
                break;
        }

        $customers = $query->paginate(10)->through(function ($customer) {
            // Calculate running amount due for ongoing reservation (no end time)
            $assignedSpace = $customer->assignedSpace; // eager loaded
            if ($assignedSpace) {
                $openRes = Reservation::where('customer_id', $customer->id)
                    ->where('space_id', $assignedSpace->id)
                    ->whereNull('end_time')
                    ->latest()
                    ->first();
                if ($openRes) {
                    $hours = now()->diffInHours($openRes->start_time);
                    $hourly = $openRes->custom_hourly_rate ?? $openRes->applied_hourly_rate;
                    $runningCost = $assignedSpace->calculateCost($hours, $hourly, $openRes->applied_discount_hours, $openRes->applied_discount_percentage);
                    // You may subtract payments here if tracking per-reservation payments
                    $customer->setAttribute('amount_due', $runningCost);
                }
            }
            return $customer;
        });

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'customers' => $customers,
            'spaceTypes' => $spaceTypes,
            'recentTransactions' => $recentTransactions,
            'activeServices' => $activeServices,
            'sort_by' => $sortBy,
            'sort_dir' => $sortDir,
        ]);
    }
}
