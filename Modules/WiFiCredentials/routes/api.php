<?php

use Illuminate\Support\Facades\Route;
use Modules\WiFiCredentials\Http\Controllers\WiFiCredentialsController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('wificredentials', WiFiCredentialsController::class)->names('api.wificredentials');
});
