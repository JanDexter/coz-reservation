<?php

use Illuminate\Support\Facades\Route;
use Modules\WiFiCredentials\Http\Controllers\WiFiCredentialsController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('wificredentials', WiFiCredentialsController::class)->names('wificredentials');
});
