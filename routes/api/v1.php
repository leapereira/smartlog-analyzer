<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\LogController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::prefix('logs')
  ->group(function () {
    Route::get('/', [LogController::class, 'index']);
    Route::post('/', [LogController::class, 'store']);
  });
