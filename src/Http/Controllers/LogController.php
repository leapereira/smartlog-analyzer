<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListLogsRequest;
use App\Http\Requests\StoreLogRequest;
use App\Http\Resources\LogResource;
use App\Models\Log;
use App\Queries\ListLogsQuery;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

#[Group('Logs')]
class LogController extends Controller
{
  /**
   * Consultar logs.
   *
   * Filtra por servicio, nivel y fechas. Devuelve un listado paginado y los
   * conteos de todos los logs que coinciden con los filtros.
   */
  #[Response(200, 'Listado paginado y conteos de los logs filtrados.')]
  #[Response(401, 'La clave de SmartLog falta o es incorrecta.', type: 'array{message: string}')]
  #[Response(422, 'Los filtros son inválidos.', type: 'array{message: string, errors: array<string, list<string>>}')]
  #[Response(429, 'Se superó el límite de solicitudes.', type: 'array{message: string}')]
  #[Response(503, 'El servicio de caché no está disponible.', type: 'array{message: string}')]
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

  /**
   * Registrar un log.
   *
   * Guarda el servicio, nivel, mensaje y fecha del evento. Devuelve el log creado.
   */
  #[Response(201, 'Log creado.')]
  #[Response(401, 'La clave de SmartLog falta o es incorrecta.', type: 'array{message: string}')]
  #[Response(422, 'Los datos del log son inválidos.', type: 'array{message: string, errors: array<string, list<string>>}')]
  #[Response(429, 'Se superó el límite de solicitudes.', type: 'array{message: string}')]
  #[Response(503, 'El servicio de caché no está disponible.', type: 'array{message: string}')]
  public function store(StoreLogRequest $request): JsonResponse
  {
    $log = Log::create($request->validated());
    $logResource = new LogResource($log);

    return $logResource
      ->response()
      ->setStatusCode(JsonResponse::HTTP_CREATED);
  }
}
