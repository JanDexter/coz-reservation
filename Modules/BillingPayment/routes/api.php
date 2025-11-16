<?php

use Illuminate\Support\Facades\Route;
use Modules\BillingPayment\Http\Controllers\BillingPaymentController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('billingpayments', BillingPaymentController::class)->names('billingpayment');
});
