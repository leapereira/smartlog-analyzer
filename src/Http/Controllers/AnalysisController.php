<?php

namespace App\Http\Controllers;

use App\Actions\AnalyzeLogs;
use App\Exceptions\AnalysisFailedException;
use App\Exceptions\AnalysisInProgressException;
use App\Exceptions\AnalyzerTimeoutException;
use App\Http\Requests\AnalyzeLogsRequest;
use App\Http\Resources\AnalysisResource;
use App\Models\Analysis;
use Illuminate\Http\JsonResponse;

class AnalysisController extends Controller
{
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

  public function show(int $id): JsonResponse
  {
    $analysis = Analysis::findOrFail($id);
    $analysisResource = new AnalysisResource($analysis);

    return $analysisResource
      ->response();
  }
}
