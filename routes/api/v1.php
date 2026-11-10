<?php

use App\Http\Controllers\AnalysisController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\LogController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)
  ->withoutMiddleware('throttle:api');

Route::prefix('logs')
  ->group(function () {
    Route::get('/', [LogController::class, 'index']);
    Route::post('/', [LogController::class, 'store']);
    Route::post('/analyze', [AnalysisController::class, 'store']);
  });
