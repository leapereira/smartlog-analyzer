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
  public function show(int $id): JsonResponse
  {
    $analysis = Analysis::findOrFail($id);
    $analysisResource = new AnalysisResource($analysis);

    return $analysisResource
      ->response();
  }
}
