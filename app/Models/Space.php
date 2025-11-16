<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Space extends Model
{
    use HasFactory;

    protected $fillable = [
        'space_type_id',
        'name',
        'status',
        'current_customer_id',
        'occupied_from',
        'occupied_until',
        'notes',
        'hourly_rate',
        'discount_hours',
        'discount_percentage',
        'custom_rates',
    ];

    protected $casts = [
        'occupied_from' => 'datetime',
        'occupied_until' => 'datetime',
        'custom_rates' => 'array',
    ];

    public function spaceType()
    {
        return $this->belongsTo(SpaceType::class);
    }

    public function currentCustomer()
    {
        return $this->belongsTo(Customer::class, 'current_customer_id');
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'available' => 'bg-green-100 text-green-800',
            'occupied' => 'bg-red-100 text-red-800',
            'maintenance' => 'bg-yellow-100 text-yellow-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }

    public function isAvailable()
    {
        return $this->status === 'available' && !$this->current_customer_id;
    }

    public function occupy($customerId, $from = null, $until = null)
    {
        $this->update([
            'status' => 'occupied',
            'current_customer_id' => $customerId,
            'occupied_from' => $from ?? now(),
            'occupied_until' => $until,
        ]);

        // Snapshot current effective pricing so future changes won't affect this reservation
        $effectiveHourly = $this->getEffectiveHourlyRate();
        $discountHours = $this->discount_hours ?? $this->spaceType->default_discount_hours;
        $discountPct = $this->discount_percentage ?? $this->spaceType->default_discount_percentage;

        Reservation::create([
            'user_id' => Auth::id() ?? null, // Allow null if no user is authenticated
            'customer_id' => $customerId,
            'space_id' => $this->id,
            'start_time' => $from ?? now(),
            'end_time' => $until,
            'applied_hourly_rate' => $effectiveHourly,
            'applied_discount_hours' => $discountHours,
            'applied_discount_percentage' => $discountPct,
        ]);

        // Update space type available slots
        $this->spaceType->decrement('available_slots');
    }

    public function release()
    {
        // Find active reservation (not yet ended)
        $reservation = Reservation::where('space_id', $this->id)
            ->whereNull('end_time')
            ->latest()
            ->first();
            
        if ($reservation) {
            // Calculate hours from start to now; keep integer hours as before
            $hours = now()->diffInHours($reservation->start_time);
            // Prefer custom hourly rate when provided; otherwise use applied snapshot values
            $hourly = $reservation->custom_hourly_rate ?? $reservation->applied_hourly_rate;
            $discountHours = $reservation->applied_discount_hours;
            $discountPct = $reservation->applied_discount_percentage;

            $cost = $this->calculateCost($hours, $hourly, $discountHours, $discountPct);

            // Update reservation: set end_time and cost
            // Mark as 'completed' (unpaid) - they can pay later via transactions tab
            // If already paid (payment made before release), keep it as paid
            $status = $reservation->status === 'paid' ? 'paid' : 'completed';
            
            $reservation->update([
                'end_time' => now(),
                'cost' => $cost,
                'status' => $status,
            ]);
        }

        $this->update([
            'status' => 'available',
            'current_customer_id' => null,
            'occupied_from' => null,
            'occupied_until' => null,
        ]);

        // Update space type available slots
        $this->spaceType->increment('available_slots');
    }

    public function getEffectiveHourlyRate()
    {
        return $this->hourly_rate ?: ($this->spaceType->hourly_rate ?? $this->spaceType->default_price);
    }

    public function calculateCost($hours, $customHourlyRate = null, $overrideDiscountHours = null, $overrideDiscountPct = null)
    {
        $hourlyRate = $customHourlyRate ?? $this->getEffectiveHourlyRate();
        $discountHours = $overrideDiscountHours ?? ($this->discount_hours ?? $this->spaceType->default_discount_hours);
        $discountPercentage = $overrideDiscountPct ?? ($this->discount_percentage ?? $this->spaceType->default_discount_percentage);

        // Round hours up to nearest 0.5 to avoid undercharging and give clearer estimates
        $hoursRounded = ceil($hours * 2) / 2;

        $totalCost = $hourlyRate * $hoursRounded;

        if ($discountHours && $discountPercentage && $hoursRounded >= $discountHours) {
            $discountAmount = ($totalCost * $discountPercentage) / 100;
            $totalCost -= $discountAmount;
        }

        return round($totalCost, 2);
    }

    public function getTimeUntilFree()
    {
        if ($this->status !== 'occupied' || !$this->occupied_until) {
            return null;
        }

        $now = now();
        $until = $this->occupied_until;

        if ($until <= $now) {
            return 'Available now';
        }

        $diff = $until->diff($now);
        
        if ($diff->d > 0) {
            return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ' . $diff->h . 'h';
        } elseif ($diff->h > 0) {
            return $diff->h . 'h ' . $diff->i . 'm';
        } else {
            return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '');
        }
    }

    public function isExpiringSoon($hours = 1)
    {
        if ($this->status !== 'occupied' || !$this->occupied_until) {
            return false;
        }

        return $this->occupied_until <= now()->addHours($hours);
    }
}
