<?php

use Illuminate\Support\Facades\Route;
use Modules\RefundManagement\Http\Controllers\RefundManagementController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('refundmanagements', RefundManagementController::class)->names('refundmanagement');
});
