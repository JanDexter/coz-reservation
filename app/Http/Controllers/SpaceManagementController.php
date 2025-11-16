<?php

namespace App\Http\Controllers;

use App\Models\Space;
use App\Models\SpaceType;
use App\Models\Customer;
use App\Models\User;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class SpaceManagementController extends Controller
{
    public function index()
    {
        $now = Carbon::now();
        $nowPlusBuffer = $now->copy()->addMinute(); // Create once, reuse
        
        $spaceTypes = SpaceType::with([
            'spaces' => function($query) use ($now, $nowPlusBuffer) {
                $query->with([
                    'currentCustomer',
                    'reservations' => function($q) use ($now, $nowPlusBuffer) {
                        // Get the currently active reservation for this space
                        // Active = started, not yet ended (end_time is null)
                        $q->whereNull('end_time')  // Only reservations without an end_time are truly active
                        ->where('start_time', '<=', $nowPlusBuffer)
                        ->where(function($sub) {
                            $sub->whereNull('status')
                              ->orWhereNotIn('status', ['cancelled']);  // Exclude only cancelled
                        })
                        ->with('customer')
                        ->orderBy('start_time', 'asc');
                    }
                ]);
            },
            'reservations' => function($query) use ($now, $nowPlusBuffer) {
                // Get active reservations that haven't been completed or cancelled yet
                // Active = has not been ended yet (end_time is null)
                $query->whereNull('end_time')  // Only reservations without an end_time
                ->where(function($q) {
                    $q->whereNull('status')
                      ->orWhereNotIn('status', ['cancelled']);  // Exclude only cancelled
                })
                ->with('customer')
                ->orderBy('start_time', 'asc');
            }
        ])->get();

        // Transform to include reservation info in spaces
        $spaceTypes->each(function($spaceType) use ($now, $nowPlusBuffer) {
            // Update each space with dynamic status based on current active reservation
            $spaceType->spaces->each(function($space) use ($now) {
                $activeReservation = $space->reservations->first();
                
                // Determine status: prioritize active reservation, then check database status
                if ($activeReservation) {
                    // There's an active reservation right now
                    $space->dynamic_status = 'occupied';
                    $space->active_reservation = $activeReservation;
                    $space->current_customer_name = $activeReservation->customer->display_name ?? $activeReservation->customer->name;
                } elseif ($space->status === 'occupied' && $space->current_customer_id) {
                    // Space is marked as occupied in database (reservation might be just created or slightly in future)
                    $space->dynamic_status = 'occupied';
                    $space->active_reservation = null;
                    $space->current_customer_name = $space->currentCustomer 
                        ? ($space->currentCustomer->display_name ?? $space->currentCustomer->name)
                        : null;
                } else {
                    // No active reservation and not marked as occupied
                    $space->dynamic_status = $space->status; // Use actual status (available/maintenance/etc)
                    $space->active_reservation = null;
                    $space->current_customer_name = null;
                }
            });
            
            // Get unassigned reservations (no space_id) for this space type
            $unassignedReservations = $spaceType->reservations
                ->whereNull('space_id')
                ->filter(function($reservation) use ($nowPlusBuffer, $now) {
                    // Only show if it's currently active (started or starting soon, but not ended)
                    // Include reservations starting within 1 minute to handle timing issues
                    return $reservation->start_time <= $nowPlusBuffer &&
                           ($reservation->end_time === null || $reservation->end_time > $now);
                });
            
            // Store for frontend access
            $spaceType->unassigned_reservations = $unassignedReservations->values();
        });

        // Build a fully flattened customer/user list for the frontend selector
        $customerRecords = Customer::with('user')
            ->where('status', 'active')
            ->orderBy('name')
            ->get()
            ->map(function (Customer $customer) {
                return [
                    'id' => (string) $customer->getKey(),
                    'display_name' => $customer->name ?: ($customer->company_name ?: $customer->email),
                    'name' => $customer->name,
                    'company_name' => $customer->company_name,
                    'contact_person' => $customer->contact_person,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                    'user_id' => optional($customer->user)->getKey(),
                    'is_user_only' => false,
                ];
            })
            ->values()
            ->all();

        $existingUserIds = collect($customerRecords)
            ->pluck('user_id')
            ->filter()
            ->all();

        $userOnlyRecords = User::query()
            ->where('is_active', true)
            ->when(!empty($existingUserIds), function ($query) use ($existingUserIds) {
                $query->whereNotIn('id', $existingUserIds);
            })
            ->orderBy('name')
            ->get()
            ->map(function (User $user) {
                return [
                    'id' => 'user_' . $user->getKey(),
                    'display_name' => $user->name ?: $user->email,
                    'name' => $user->name,
                    'company_name' => null,
                    'contact_person' => null,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'user_id' => $user->getKey(),
                    'is_user_only' => true,
                ];
            })
            ->values()
            ->all();

        $allCustomers = collect($customerRecords)
            ->merge($userOnlyRecords)
            ->sortBy('display_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->map(function (array $customer) {
                unset($customer['display_name']);
                return $customer;
            })
            ->all();

        return Inertia::render('SpaceManagement/Index', [
            'spaceTypes' => $spaceTypes,
            'customers' => $allCustomers,
        ]);
    }

    public function updateDetails(Request $request, SpaceType $spaceType)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'photo' => 'nullable|image|max:5120',
            'remove_photo' => 'nullable|boolean',
        ]);

        $update = [
            'name' => strtoupper($validated['name']),
            'description' => $validated['description'] ?? null,
        ];

        $oldPhoto = $spaceType->photo_path;
        $hasNewUpload = $request->hasFile('photo');
        $shouldRemove = (bool) $request->boolean('remove_photo');

        if ($hasNewUpload) {
            $path = $request->file('photo')->store('space-types', 'public');
            $update['photo_path'] = $path;
        } elseif ($shouldRemove) {
            $update['photo_path'] = null;
        }

        $spaceType->update($update);

        // Clean up old file if replaced or removed
        if ($oldPhoto && ($hasNewUpload || $shouldRemove)) {
            try {
                Storage::disk('public')->delete($oldPhoto);
            } catch (\Throwable $e) {
                // swallow cleanup errors
            }
        }

        return redirect()->back()->with('success', "Details for {$spaceType->name} updated.");
    }

    public function updatePricing(Request $request, SpaceType $spaceType)
    {
        $validated = $request->validate([
            'hourly_rate' => 'nullable|numeric|min:0',
            'default_price' => 'nullable|numeric|min:0',
            'pricing_type' => 'nullable|in:per_person,per_reservation',
            'default_discount_hours' => 'nullable|integer|min:1',
            'default_discount_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        // If only default_price provided and no hourly_rate, mirror to hourly_rate for consistency
        if (isset($validated['default_price']) && !isset($validated['hourly_rate'])) {
            $validated['hourly_rate'] = $validated['default_price'];
        }

        $spaceType->update($validated);

        return redirect()->back()->with('success', "Pricing for {$spaceType->name} updated successfully.");
    }

    public function releaseSpace(Space $space)
    {
        \Log::info('Release space requested', [
            'space_id' => $space->id,
            'space_name' => $space->name,
            'current_status' => $space->status,
            'customer_id' => $space->current_customer_id,
        ]);

        if ($space->status === 'occupied') {
            $space->release();
            
            // Reload the space to verify the changes
            $space->refresh();
            
            \Log::info('Space released', [
                'space_id' => $space->id,
                'new_status' => $space->status,
                'customer_id' => $space->current_customer_id,
            ]);
            
            return redirect()->back()->with('success', "Space {$space->name} has been released.");
        }

        \Log::warning('Release failed - space not occupied', [
            'space_id' => $space->id,
            'status' => $space->status,
        ]);

        return redirect()->back()->with('error', 'Space is not currently occupied.');
    }

    public function assignSpace(Request $request, Space $space)
    {
        // Log incoming request for debugging
        \Log::info('Assignment request received', [
            'space_id' => $space->id,
            'request_data' => $request->all(),
            'server_time' => now()->toDateTimeString(),
        ]);

        $validated = $request->validate([
            'customer_id' => 'required',
            'occupied_until' => 'nullable|date|after:now',
            'custom_hourly_rate' => 'nullable|numeric|min:0',
            'start_time' => 'nullable|date', // Remove strict time validation - we'll check manually
        ]);

        \Log::info('Validation passed', ['validated' => $validated]);

        // Handle customer_id which could be 'user_123' or actual customer ID
        $customerId = $this->resolveCustomerId($validated['customer_id']);
        if (!$customerId) {
            \Log::error('Failed to resolve customer ID', ['input' => $validated['customer_id']]);
            return redirect()->back()->with('error', 'Invalid customer or user selection.');
        }

        // Validate start_time manually with a 2-minute buffer for clock differences
        if (isset($validated['start_time'])) {
            $startTime = Carbon::parse($validated['start_time']);
            $twoMinutesAgo = now()->subMinutes(2);
            
            if ($startTime->lt($twoMinutesAgo)) {
                \Log::warning('Start time too far in past', [
                    'start_time' => $startTime->toDateTimeString(),
                    'threshold' => $twoMinutesAgo->toDateTimeString(),
                ]);
                return redirect()->back()->with('error', 'Start time cannot be in the past.');
            }
        }

        \Log::info('Checking space availability', [
            'space_id' => $space->id,
            'space_status' => $space->status,
            'current_customer_id' => $space->current_customer_id,
            'is_available' => $space->isAvailable(),
        ]);

        if (!$space->isAvailable()) {
            \Log::warning('Space not available', [
                'space_id' => $space->id,
                'status' => $space->status,
                'customer_id' => $space->current_customer_id,
            ]);
            return redirect()->back()->with('error', 'Space is not available.');
        }

        $from = $validated['start_time'] ?? now();
        $until = $validated['occupied_until'] ?? null;

        // Check for scheduling conflicts on this specific space
        if ($space->id) {
            $conflictExists = Reservation::query()
                ->active()
                ->where('space_id', $space->id)
                ->overlapping(Carbon::parse($from), $until ? Carbon::parse($until) : null)
                ->exists();

            \Log::info('Conflict check', [
                'space_id' => $space->id,
                'conflict_exists' => $conflictExists,
                'from' => $from,
                'until' => $until,
            ]);

            if ($conflictExists) {
                \Log::warning('Scheduling conflict detected', [
                    'space_id' => $space->id,
                    'from' => $from,
                    'until' => $until,
                ]);
                return redirect()->back()->with('error', 'This space has a conflicting reservation during the selected time.');
            }
        }

        $space->occupy(
            $customerId,
            $from,
            $until
        );

        // Update latest reservation for this space to include custom rate (if provided)
        $reservation = \App\Models\Reservation::where('space_id', $space->id)
            ->where('customer_id', $customerId)
            ->latest()
            ->first();
            
        \Log::info('Space assigned', [
            'space_id' => $space->id,
            'space_status' => $space->fresh()->status,
            'customer_id' => $customerId,
            'reservation_id' => $reservation ? $reservation->id : null,
            'reservation_start' => $reservation ? $reservation->start_time : null,
        ]);
        
        if ($reservation) {
            if (isset($validated['custom_hourly_rate'])) {
                $reservation->update(['custom_hourly_rate' => $validated['custom_hourly_rate']]);
            }
            // If end time provided at assignment, compute an initial cost estimate using snapshot values
            if ($until) {
                $hours = max(0, \Carbon\Carbon::parse($from)->diffInHours(\Carbon\Carbon::parse($until)));
                $hourly = $reservation->custom_hourly_rate ?? $reservation->applied_hourly_rate;
                $estimated = $space->calculateCost($hours, $hourly, $reservation->applied_discount_hours, $reservation->applied_discount_percentage);
                $reservation->update(['cost' => $estimated]);
            }
        }

        return redirect()->back()->with('success', "Space {$space->name} has been assigned.");
    }

    public function startOpenTime(Request $request, Space $space)
    {
        $request->validate([
            'customer_id' => 'required',
        ]);

        // Handle customer_id which could be 'user_123' or actual customer ID
        $customerId = $this->resolveCustomerId($request->customer_id);
        if (!$customerId) {
            return redirect()->back()->with('error', 'Invalid customer or user selection.');
        }

        // Dynamic availability: ensure no active reservation is currently using this space
        $now = Carbon::now();
        $hasActive = Reservation::where('space_id', $space->id)
            ->where(function ($q) {
                $q->whereNull('status')
                  ->orWhereNotIn('status', ['completed', 'cancelled']);
            })
            ->where('start_time', '<=', $now)
            ->where(function ($q) use ($now) {
                $q->whereNull('end_time')->orWhere('end_time', '>', $now);
            })
            ->exists();

        if ($hasActive) {
            return back()->with('error', 'Space is currently in use.');
        }

        DB::transaction(function () use ($customerId, $space) {
            // Get hourly rate from space or space type
            $hourlyRate = $space->hourly_rate ?? $space->spaceType->hourly_rate ?? $space->spaceType->default_price;

            Reservation::create([
                'user_id' => Auth::id(),
                'customer_id' => $customerId,
                'space_id' => $space->id,
                'space_type_id' => $space->space_type_id,
                'start_time' => Carbon::now(),
                'end_time' => null, // Open time - will be set when ended
                'applied_hourly_rate' => $hourlyRate,
                'status' => 'active',
                'is_open_time' => true,
                'payment_method' => 'cash', // Open time is cash at end
                'pax' => 1,
                'hours' => 0,   // initialize to 0; will be computed on end
                'cost' => 0,    // initialize to 0; will be computed on end
            ]);

            // Occupy the space so it shows as occupied
            $space->update([
                'status' => 'occupied',
                'current_customer_id' => $customerId,
                'occupied_from' => Carbon::now(),
                'occupied_until' => null, // Open ended
            ]);
            
            // Decrement available slots
            $space->spaceType->decrement('available_slots');
        });

        return back()->with('success', 'Open time started successfully.');
    }

    public function endOpenTime(Space $space)
    {
        $reservation = Reservation::where('space_id', $space->id)
            ->where('is_open_time', true)
            ->whereNull('end_time')
            ->first();

        if (!$reservation) {
            return back()->with('error', 'No active open time session found for this space.');
        }

        DB::transaction(function () use ($space, $reservation) {
            $endTime = Carbon::now();
            $startTime = Carbon::parse($reservation->start_time);
            // Calculate duration in minutes, then convert to hours (rounded up)
            $durationInMinutes = $startTime->diffInMinutes($endTime);
            $durationInHours = ceil($durationInMinutes / 60); // Round up to nearest hour
            if ($durationInHours < 1) {
                $durationInHours = 1; // Minimum 1 hour charge
            }
            // Get the hourly rate from the space or space type
            $hourlyRate = $space->hourly_rate ?? $space->spaceType->hourly_rate ?? $space->spaceType->default_price;
            $totalCost = $durationInHours * $hourlyRate;
            $reservation->hours = $durationInHours;
            $reservation->cost = $totalCost;
            $reservation->applied_hourly_rate = $hourlyRate;
            $reservation->end_time = $endTime;
            $reservation->status = 'completed'; // Mark as completed (unpaid) - can pay later
            // Do NOT mark as paid; payment must be processed separately
            $reservation->amount_paid = 0;
            $reservation->save();

            // Release the space
            $space->update([
                'status' => 'available',
                'current_customer_id' => null,
                'occupied_from' => null,
                'occupied_until' => null,
            ]);
            
            // Increment available slots back
            $space->spaceType->increment('available_slots');
        });

        return back()->with('success', 'Open time ended successfully. Total cost calculated.');
    }

    // Create a new space type or increase slots for an existing one (sub spaces)
    public function storeSpaceType(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'hourly_rate' => 'required|numeric|min:0',
            'pricing_type' => 'nullable|in:per_person,per_reservation',
            'default_discount_hours' => 'nullable|integer|min:1',
            'default_discount_percentage' => 'nullable|numeric|min:0|max:100',
            'initial_slots' => 'required|integer|min:1',
        ]);

        // Try to find existing by name (case-insensitive); if exists, add spaces; else create new type
        $spaceType = SpaceType::whereRaw('LOWER(name) = ?', [strtolower($validated['name'])])->first();
        if (!$spaceType) {
            $spaceType = SpaceType::create([
                'name' => strtoupper($validated['name']),
                'default_price' => $validated['hourly_rate'],
                'hourly_rate' => $validated['hourly_rate'],
                'pricing_type' => $validated['pricing_type'] ?? 'per_person',
                'default_discount_hours' => $validated['default_discount_hours'] ?? null,
                'default_discount_percentage' => $validated['default_discount_percentage'] ?? null,
                'total_slots' => $validated['initial_slots'],
                'available_slots' => $validated['initial_slots'],
                'description' => $validated['description'] ?? null,
            ]);
        } else {
            // Increase declared slot capacity, availability increments by newly created spaces
            $spaceType->increment('total_slots', $validated['initial_slots']);
            $spaceType->increment('available_slots', $validated['initial_slots']);
        }

        // Create concrete spaces under this space type
        $count = $validated['initial_slots'];
        for ($i = 0; $i < $count; $i++) {
            $nextNum = $spaceType->spaces()->count() + 1;
            Space::create([
                'space_type_id' => $spaceType->id,
                'name' => $spaceType->name . ' ' . $nextNum,
                'status' => 'available',
                'hourly_rate' => $spaceType->hourly_rate,
                'discount_hours' => $spaceType->default_discount_hours,
                'discount_percentage' => $spaceType->default_discount_percentage,
            ]);
        }

        return redirect()->back()->with('success', 'Space type updated and spaces created.');
    }

    // Create a single space under a space type with custom properties
    public function storeSpace(Request $request, SpaceType $spaceType)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'hourly_rate' => 'nullable|numeric|min:0',
            'discount_hours' => 'nullable|integer|min:1',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        $nextNum = $spaceType->spaces()->count() + 1;
        $space = Space::create([
            'space_type_id' => $spaceType->id,
            'name' => $validated['name'] ?? ($spaceType->name . ' ' . $nextNum),
            'status' => 'available',
            'hourly_rate' => $validated['hourly_rate'] ?? $spaceType->hourly_rate,
            'discount_hours' => $validated['discount_hours'] ?? $spaceType->default_discount_hours,
            'discount_percentage' => $validated['discount_percentage'] ?? $spaceType->default_discount_percentage,
        ]);

        // Update slots counters
        $spaceType->increment('total_slots', 1);
        $spaceType->increment('available_slots', 1);

        return redirect()->back()->with('success', 'New space created: ' . $space->name);
    }

    // Permanently remove a single space (only if available)
    public function destroySpace(Space $space)
    {
        if ($space->status !== 'available') {
            return redirect()->back()->with('error', 'Cannot delete an occupied space. Release it first.');
        }

        $spaceType = $space->spaceType;
        $space->delete();

        // Update counters
        if ($spaceType) {
            $spaceType->decrement('total_slots', 1);
            $spaceType->decrement('available_slots', 1);
        }

        return redirect()->back()->with('success', 'Space removed permanently.');
    }

    // Bulk remove available subspaces from a type (only removes available spaces)
    public function bulkDestroySpaces(Request $request, SpaceType $spaceType)
    {
        $data = $request->validate([
            'count' => 'required|integer|min:1',
        ]);

        $count = (int) $data['count'];
        $spaces = $spaceType->spaces()
            ->where('status', 'available')
            ->orderByDesc('id')
            ->take($count)
            ->get();

        $deleted = 0;
        foreach ($spaces as $s) {
            $s->delete();
            $deleted++;
        }

        if ($deleted > 0) {
            $spaceType->decrement('total_slots', $deleted);
            $spaceType->decrement('available_slots', $deleted);
        }

        if ($deleted === 0) {
            return redirect()->back()->with('error', 'No available spaces to remove.');
        }

        return redirect()->back()->with('success', "Removed {$deleted} available space(s).");
    }

    // Remove an entire space type (only if no occupied spaces remain)
    public function destroySpaceType(SpaceType $spaceType)
    {
        // Prevent deleting when any space is occupied
        $occupiedCount = $spaceType->spaces()->where('status', 'occupied')->count();
        if ($occupiedCount > 0) {
            return redirect()->back()->with('error', 'Cannot delete this space type while there are occupied spaces. Release them first.');
        }

        // Delete all remaining spaces under this type first
        $spaces = $spaceType->spaces()->get();
        foreach ($spaces as $s) {
            $s->delete();
        }

        // Finally, delete the space type
        $spaceType->delete();

        return redirect()->back()->with('success', 'Space type removed successfully.');
    }

    /**
     * Resolve customer ID from the input which could be 'user_123' or a customer ID
     * If user ID, auto-create customer record if needed
     */
    private function resolveCustomerId($input)
    {
        // Check if it's a user-prefixed ID
        if (is_string($input) && str_starts_with($input, 'user_')) {
            $userId = (int) str_replace('user_', '', $input);
            $user = User::find($userId);
            
            if (!$user) {
                return null;
            }

            // Check if customer already exists for this user
            $customer = Customer::where('user_id', $userId)->first();
            
            if (!$customer) {
                // Auto-create customer record
                $customer = Customer::create([
                    'user_id' => $userId,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'status' => 'active',
                ]);
            }

            return $customer->id;
        }

        // Otherwise, verify it's a valid customer ID
        if (Customer::where('id', $input)->exists()) {
            return $input;
        }

        return null;
    }
}
