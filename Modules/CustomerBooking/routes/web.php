<?php

use Illuminate\Support\Facades\Route;
use Modules\CustomerBooking\Http\Controllers\CustomerBookingController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('customerbookings', CustomerBookingController::class)->names('customerbooking');
});
