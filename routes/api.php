<?php

use App\Http\Controllers\Api\EarthquakeSafetyTestController;
use Illuminate\Support\Facades\Route;

Route::prefix('earthquake-safety-test')->group(function () {
    Route::get('/modules', [EarthquakeSafetyTestController::class, 'indexModules']);
    Route::get('/modules/{module}/scenarios', [EarthquakeSafetyTestController::class, 'scenarios']);
    Route::post('/modules/{module}/submit', [EarthquakeSafetyTestController::class, 'submit']);
});