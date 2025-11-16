<?php

use Illuminate\Support\Facades\Route;
use Modules\CalendarView\Http\Controllers\CalendarViewController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('calendarviews', CalendarViewController::class)->names('calendarview');
});
