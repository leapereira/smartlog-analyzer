<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\LogController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);
Route::post('/logs', [LogController::class, 'store']);
