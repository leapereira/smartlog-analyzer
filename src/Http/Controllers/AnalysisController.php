<?php

namespace App\Http\Controllers;

use App\Actions\AnalyzeLogs;
use App\Exceptions\AnalysisFailedException;
use App\Exceptions\AnalysisInProgressException;
use App\Exceptions\AnalyzerTimeoutException;
use App\Http\Requests\AnalyzeLogsRequest;
use App\Http\Resources\AnalysisResource;
use App\Models\Analysis;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

#[Group('Análisis')]
class AnalysisController extends Controller
{
  /**
   * Analizar los logs seleccionados.
   *
   * Recibe los IDs de los logs y puede reutilizar un análisis completado.
   * Con cache=false solicita una ejecución nueva.
   */
  #[Response(200, 'Se reutilizó un análisis completado.', type: 'array{id: int}')]
  #[Response(201, 'Se creó un análisis nuevo.', type: 'array{id: int}')]
  #[Response(401, 'La clave de SmartLog falta o es incorrecta.', type: 'array{message: string}')]
  #[Response(409, 'Otro pedido está generando el mismo análisis.', type: 'array{message: string}')]
  #[Response(422, 'La selección de logs o el parámetro cache son inválidos.', type: 'array{message: string, errors: array<string, list<string>>}')]
  #[Response(429, 'Se superó el límite de solicitudes.', type: 'array{message: string}')]
  #[Response(502, 'El analizador falló o devolvió una respuesta inválida.', type: 'array{id: int, message: string}')]
  #[Response(503, 'Redis no está disponible para iniciar la operación.', type: 'array{message: string}')]
  #[Response(504, 'Se agotó el tiempo de espera del analizador.', type: 'array{id: int, message: string}')]
  public function store(AnalyzeLogsRequest $request, AnalyzeLogs $analyzeLogs): JsonResponse
  {
    try {
      $execution = $analyzeLogs->execute(
        $request->validated('log_ids'),
        $request->useCache(),
      );
    } catch (AnalysisInProgressException $exception) {
      return response()->json([
        'message' => $exception->getMessage(),
      ], JsonResponse::HTTP_CONFLICT);
    } catch (AnalysisFailedException $exception) {
      return response()->json([
        'id' => $exception->analysis->getKey(),
        'message' => $exception->getMessage(),
      ], $exception->getPrevious() instanceof AnalyzerTimeoutException
        ? JsonResponse::HTTP_GATEWAY_TIMEOUT
        : JsonResponse::HTTP_BAD_GATEWAY);
    }

    return response()->json([
      'id' => $execution->analysis->getKey(),
    ], $execution->fromCache
      ? JsonResponse::HTTP_OK
      : JsonResponse::HTTP_CREATED);
  }

  /**
   * Consultar un análisis.
   *
   * Devuelve su estado, resultado y error, si lo hubiera.
   */
  #[Response(200, 'Estado y resultado del análisis.')]
  #[Response(401, 'La clave de SmartLog falta o es incorrecta.', type: 'array{message: string}')]
  #[Response(404, 'No existe un análisis con ese ID.', type: 'array{message: string}')]
  #[Response(429, 'Se superó el límite de solicitudes.', type: 'array{message: string}')]
  #[Response(503, 'El servicio de caché no está disponible.', type: 'array{message: string}')]
  public function show(int $id): JsonResponse
  {
    $analysis = Analysis::findOrFail($id);
    $analysisResource = new AnalysisResource($analysis);

    return $analysisResource
      ->response();
  }
}
