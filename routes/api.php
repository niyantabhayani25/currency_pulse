<?php

declare(strict_types=1);

use App\Http\Controllers\Api\CurrencyController;
use App\Http\Controllers\Api\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/currencies', [CurrencyController::class, 'index'])->name('api.currencies.index');
    Route::put('/currencies', [CurrencyController::class, 'update'])->name('api.currencies.update');

    Route::get('/reports',             [ReportController::class, 'index'])->name('api.reports.index');
    Route::post('/reports',            [ReportController::class, 'store'])->name('api.reports.store');
    Route::get('/reports/{report}',    [ReportController::class, 'show'])->name('api.reports.show');
    Route::delete('/reports/{report}', [ReportController::class, 'destroy'])->name('api.reports.destroy');
});
