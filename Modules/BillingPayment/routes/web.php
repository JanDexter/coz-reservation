<?php

use Illuminate\Support\Facades\Route;
use Modules\BillingPayment\Http\Controllers\BillingPaymentController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('billingpayments', BillingPaymentController::class)->names('billingpayment');
});
