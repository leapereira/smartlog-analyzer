<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class HealthController extends Controller
{
  public function __invoke(): JsonResponse
  {
    $databaseAvailable = false;
    $redisAvailable = false;

    try {
      $databaseAvailable = DB::select('SELECT 1') !== [];
    } catch (Throwable) {
      // La respuesta solo informa que el servicio no está disponible
    }

    try {
      $pingResponse = Redis::ping();
      $redisAvailable = $pingResponse !== false && $pingResponse !== null;
    } catch (Throwable) {
      // La respuesta solo informa que el servicio no está disponible
    }

    $healthy = $databaseAvailable && $redisAvailable;

    return response()->json(
      ['status' => $healthy ? 'ok' : 'unavailable'],
      $healthy ? JsonResponse::HTTP_OK : JsonResponse::HTTP_SERVICE_UNAVAILABLE,
    );
  }
}
