<?php

use App\Http\Controllers\Api\EarthquakeSafetyTestController;
use Illuminate\Support\Facades\Route;

Route::post('/session/start', [EarthquakeSafetyTestController::class, 'startSession']);
Route::post('/session/events', [EarthquakeSafetyTestController::class, 'trackSessionEvent'])->middleware('api.token');
Route::get('/resources', [EarthquakeSafetyTestController::class, 'resources']);
Route::get('/modules', [EarthquakeSafetyTestController::class, 'modules']);
Route::get('/modules/{id}/scenarios', [EarthquakeSafetyTestController::class, 'scenarios']);
Route::get('/modules/{id}/scenarios/next', [EarthquakeSafetyTestController::class, 'nextScenario']);
Route::post('/modules/{id}/submit', [EarthquakeSafetyTestController::class, 'submit'])->middleware('api.token');
Route::get('/results/{user_name}', [EarthquakeSafetyTestController::class, 'results'])->middleware('api.token');
Route::get('/admin/analytics-summary', [EarthquakeSafetyTestController::class, 'analyticsSummary'])->middleware('api.token');
Route::get('/admin/risk-distribution', [EarthquakeSafetyTestController::class, 'riskDistribution'])->middleware('api.token');
Route::get('/prototype/config', [EarthquakeSafetyTestController::class, 'prototypeConfig']);
Route::post('/prototype/submit', [EarthquakeSafetyTestController::class, 'prototypeSubmit'])->middleware('api.token');
Route::get('/prototype/analytics', [EarthquakeSafetyTestController::class, 'prototypeAnalytics'])->middleware('api.token');

Route::prefix('earthquake-safety-test')->group(function () {
    Route::post('/session/start', [EarthquakeSafetyTestController::class, 'startSession']);
    Route::post('/session/events', [EarthquakeSafetyTestController::class, 'trackSessionEvent'])->middleware('api.token');
    Route::get('/resources', [EarthquakeSafetyTestController::class, 'resources']);
    Route::get('/modules', [EarthquakeSafetyTestController::class, 'modules']);
    Route::get('/modules/{id}/scenarios', [EarthquakeSafetyTestController::class, 'scenarios']);
    Route::get('/modules/{id}/scenarios/next', [EarthquakeSafetyTestController::class, 'nextScenario']);
    Route::post('/modules/{id}/submit', [EarthquakeSafetyTestController::class, 'submit'])->middleware('api.token');
    Route::get('/results/{user_name}', [EarthquakeSafetyTestController::class, 'results'])->middleware('api.token');
    Route::get('/admin/analytics-summary', [EarthquakeSafetyTestController::class, 'analyticsSummary'])->middleware('api.token');
    Route::get('/admin/risk-distribution', [EarthquakeSafetyTestController::class, 'riskDistribution'])->middleware('api.token');
    Route::get('/prototype/config', [EarthquakeSafetyTestController::class, 'prototypeConfig']);
    Route::post('/prototype/submit', [EarthquakeSafetyTestController::class, 'prototypeSubmit'])->middleware('api.token');
    Route::get('/prototype/analytics', [EarthquakeSafetyTestController::class, 'prototypeAnalytics'])->middleware('api.token');
});
