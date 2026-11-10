<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListLogsRequest;
use App\Http\Requests\StoreLogRequest;
use App\Http\Resources\LogResource;
use App\Models\Log;
use App\Queries\ListLogsQuery;
use Illuminate\Http\JsonResponse;

class LogController extends Controller
{
  public function index(ListLogsRequest $request, ListLogsQuery $query): JsonResponse
  {
    $result = $query->execute($request->validated());
    $logs = $result['paginator'];

    return response()->json([
      'logs' => LogResource::collection($logs->getCollection())->resolve($request),
      'summary' => [
        'by_service' => $result['by_service'],
        'by_level' => $result['by_level'],
      ],
      'pagination' => [
        'page' => $logs->currentPage(),
        'per_page' => $logs->perPage(),
        'total' => $logs->total(),
        'last_page' => $logs->lastPage(),
      ],
    ]);
  }

  public function store(StoreLogRequest $request): JsonResponse
  {
    $log = Log::create($request->validated());
    $logResource = new LogResource($log);

    return $logResource
      ->response()
      ->setStatusCode(JsonResponse::HTTP_CREATED);
  }
}
