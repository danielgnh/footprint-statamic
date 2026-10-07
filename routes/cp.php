<?php

use Danielgnh\FootprintStatamic\Http\Controllers\InteractionsController;
use Danielgnh\FootprintStatamic\Http\Controllers\VisitController;
use Illuminate\Support\Facades\Route;

Route::prefix('footprint')->name('footprint.')->group(function () {
    Route::get('visits/{visit}', [VisitController::class, 'show'])->name('visits.show');
    Route::get('entries/{entry}/{part}', [InteractionsController::class, 'show'])->whereIn('part', InteractionsController::PARTS)->name('interactions.show');
});
