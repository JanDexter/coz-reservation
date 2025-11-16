<?php

use Illuminate\Support\Facades\Route;
use Modules\CustomerBooking\Http\Controllers\CustomerBookingController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('customerbookings', CustomerBookingController::class)->names('customerbooking');
});
