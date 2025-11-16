<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'user_id',
        'name',
        'company_name',
        'contact_person',
        'email',
        'email_verified_at',
        'created_by_admin',
        'phone',
        'address',
        'website',
        'status',
        'notes',
        'amount_paid',
        'space_type_id',
    ];

    protected $casts = [
        'amount_paid' => 'decimal:2',
        'email_verified_at' => 'datetime',
        'created_by_admin' => 'boolean',
    ];

    // Get formatted amount paid
    public function getFormattedAmountPaidAttribute()
    {
        return $this->amount_paid ? '₱' . number_format($this->amount_paid, 2) : '₱0.00';
    }

    // Removed tasks() relationship (Task Tracker deprecated)
    // public function tasks(): HasMany { return $this->hasMany(Task::class); }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function assignedSpace()
    {
        return $this->hasOne(Space::class, 'current_customer_id');
    }

    public function spaceType()
    {
        return $this->belongsTo(SpaceType::class, 'space_type_id');
    }

    /**
     * Validate if customer can make a cash booking
     * Prevents mass-booking and cancelling abuse
     * 
     * @return array ['valid' => bool, 'message' => string]
     */
    public function validateCashBooking(): array
    {
        // Rule 1: Check active cash bookings (max 2)
        $activeCashBookings = $this->reservations()
            ->whereIn('status', ['pending', 'on_hold', 'confirmed', 'active'])
            ->where('payment_method', 'cash')
            ->count();

        if ($activeCashBookings >= 2) {
            return [
                'valid' => false,
                'message' => 'You already have the maximum number of pending cash bookings.'
            ];
        }

        // Rule 2: Check cancellation rate (over 50% with 5+ total bookings)
        $totalBookings = $this->reservations()->count();
        
        if ($totalBookings > 5) {
            $totalCancellations = $this->reservations()
                ->where('status', 'cancelled')
                ->count();
            
            $cancellationRate = ($totalCancellations / $totalBookings) * 100;
            
            if ($cancellationRate > 50) {
                return [
                    'valid' => false,
                    'message' => 'Due to your cancellation history, pre-payment is required to make a new booking.'
                ];
            }
        }

        // Both checks passed
        return [
            'valid' => true,
            'message' => 'Validation passed'
        ];
    }

    /**
     * Get customer's booking statistics
     * 
     * @return array
     */
    public function getBookingStats(): array
    {
        $totalBookings = $this->reservations()->count();
        $activeCashBookings = $this->reservations()
            ->whereIn('status', ['pending', 'on_hold', 'confirmed', 'active'])
            ->where('payment_method', 'cash')
            ->count();
        $totalCancellations = $this->reservations()
            ->where('status', 'cancelled')
            ->count();
        
        $cancellationRate = $totalBookings > 0 
            ? ($totalCancellations / $totalBookings) * 100 
            : 0;

        return [
            'total_bookings' => $totalBookings,
            'active_cash_bookings' => $activeCashBookings,
            'total_cancellations' => $totalCancellations,
            'cancellation_rate' => round($cancellationRate, 2),
        ];
    }
}
