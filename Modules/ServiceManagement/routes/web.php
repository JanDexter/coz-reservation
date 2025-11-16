<?php

use Illuminate\Support\Facades\Route;
use Modules\ServiceManagement\Http\Controllers\ServiceManagementController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('servicemanagements', ServiceManagementController::class)->names('servicemanagement');
});
