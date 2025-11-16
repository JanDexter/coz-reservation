<?php

use Illuminate\Support\Facades\Route;
use Modules\CalendarView\Http\Controllers\CalendarViewController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('calendarviews', CalendarViewController::class)->names('calendarview');
});
