<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLogRequest;
use App\Http\Resources\LogResource;
use App\Models\Log;
use Illuminate\Http\JsonResponse;

class LogController extends Controller
{
  public function store(StoreLogRequest $request): JsonResponse
  {
    $log = Log::create($request->validated());
    $logResource = new LogResource($log);

    return $logResource
      ->response()
      ->setStatusCode(JsonResponse::HTTP_CREATED);
  }
}
