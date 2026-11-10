<?php

use App\Http\Middleware\AuthenticateApiKey;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
  ->middleware(AuthenticateApiKey::class)
  ->group(__DIR__ . '/api/v1.php');
