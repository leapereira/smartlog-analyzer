<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\AuthenticateApiKey;

Route::prefix('v1')
  ->middleware(AuthenticateApiKey::class)
  ->group(__DIR__ . '/api/v1.php');
