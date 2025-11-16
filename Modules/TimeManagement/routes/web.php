<?php

use Illuminate\Support\Facades\Route;
use Modules\TimeManagement\Http\Controllers\TimeManagementController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('timemanagements', TimeManagementController::class)->names('timemanagement');
});
