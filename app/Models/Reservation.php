<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Customer;
use App\Models\Space;
use App\Models\SpaceType;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'customer_id',
        'space_id',
        'space_type_id',
        'payment_method',
        'hours',
        'pax',
        'status',
        'hold_until',
        'notes',
        'start_time',
        'end_time',
        'cost',
        'amount_paid',
        'custom_hourly_rate',
        'applied_hourly_rate',
        'applied_discount_hours',
        'applied_discount_percentage',
        'is_open_time',
        'is_discounted',
    ];

    protected $casts = [
        'hold_until' => 'datetime',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'is_open_time' => 'boolean',
        'is_discounted' => 'boolean',
    ];

    protected $appends = [
        'total_cost',
        'effective_hourly_rate',
        'amount_remaining',
        'is_partially_paid',
        'is_fully_paid',
    ];

    /**
     * Prepare a date for array / JSON serialization.
     * Override to serialize dates in the app timezone instead of UTC
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        // Convert to Carbon instance and set to app timezone
        $carbon = Carbon::parse($date)->setTimezone(config('app.timezone'));
        
        // Return in ISO format but with the app timezone offset
        // Format: 2025-11-17T13:05:00+08:00 (Philippine time with +08:00 offset)
        return $carbon->toIso8601String();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function space()
    {
        return $this->belongsTo(Space::class);
    }

    public function spaceType()
    {
        return $this->belongsTo(SpaceType::class);
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class);
    }

    public function scopePaidOrCompleted($query)
    {
        return $query->whereIn('status', ['paid', 'completed']);
    }

    public function scopeActive($query)
    {
        // Active = reservations that occupy a slot (exclude only cancelled and completed)
        // As long as a reservation exists and isn't cancelled or completed, it takes up space
        return $query->whereNotIn('status', ['cancelled', 'completed']);
    }

    public function scopeOverlapping($query, Carbon $start, ?Carbon $end)
    {
        // If no end time provided, treat as same as start (instant check)
        $endBoundary = $end ? $end->copy() : $start->copy()->addMinute();

        // Two ranges overlap if: reservation_start < check_end AND reservation_end > check_start
        // This is the correct overlap detection algorithm
        return $query->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->where('start_time', '<', $endBoundary)
            ->where('end_time', '>', $start);
    }

    public function getEffectiveHourlyRateAttribute()
    {
        return $this->custom_hourly_rate
            ?? $this->applied_hourly_rate
            ?? optional($this->spaceType)->hourly_rate
            ?? optional($this->space)->hourly_rate
            ?? 0;
    }

    public function getTotalCostAttribute()
    {
        if (!is_null($this->cost)) {
            return (float) $this->cost;
        }

        $hours = $this->hours ?: ($this->start_time && $this->end_time ? $this->start_time->diffInHours($this->end_time) : 0);
        $rate = $this->effective_hourly_rate;

        $baseCost = $hours * $rate;

        if ($this->is_discounted) {
            $discountPct = $this->applied_discount_percentage
                ?? optional($this->space)->discount_percentage
                ?? optional($this->spaceType)->default_discount_percentage
                ?? 0;

            $baseCost -= ($baseCost * ($discountPct / 100));
        }

        return round(max($baseCost, 0), 2);
    }

    public function getAmountRemainingAttribute()
    {
        $total = $this->total_cost;
        $paid = (float) ($this->attributes['amount_paid'] ?? 0);
        return round(max($total - $paid, 0), 2);
    }

    public function getIsPartiallyPaidAttribute()
    {
        $paid = (float) ($this->attributes['amount_paid'] ?? 0);
        $total = $this->total_cost;
        return $paid > 0 && $paid < $total;
    }

    public function getIsFullyPaidAttribute()
    {
        $paid = (float) ($this->attributes['amount_paid'] ?? 0);
        $total = $this->total_cost;
        return $paid >= $total && $total > 0;
    }
}
