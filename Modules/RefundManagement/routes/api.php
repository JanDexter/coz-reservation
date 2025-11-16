<?php

use Illuminate\Support\Facades\Route;
use Modules\RefundManagement\Http\Controllers\RefundManagementController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('refundmanagements', RefundManagementController::class)->names('refundmanagement');
});
