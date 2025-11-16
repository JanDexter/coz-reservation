<?php

use Illuminate\Support\Facades\Route;
use Modules\ReservationManagement\Http\Controllers\ReservationManagementController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('reservationmanagements', ReservationManagementController::class)->names('reservationmanagement');
});
