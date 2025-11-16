<?php

use Illuminate\Support\Facades\Route;
use Modules\TimeManagement\Http\Controllers\TimeManagementController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('timemanagements', TimeManagementController::class)->names('timemanagement');
});
