<?php

namespace App\Http\Controllers;

use App\Actions\AnalyzeLogs;
use App\Enums\AnalysisStatusEnum;
use App\Http\Requests\AnalyzeLogsRequest;
use App\Http\Resources\AnalysisResource;
use App\Models\Analysis;
use Illuminate\Http\JsonResponse;

class AnalysisController extends Controller
{
  public function store(AnalyzeLogsRequest $request, AnalyzeLogs $analyzeLogs): JsonResponse
  {
    $analysis = $analyzeLogs->execute($request->validated('log_ids'));

    if ($analysis->getAttribute('status') === AnalysisStatusEnum::Failed) {
      return response()->json([
        'id' => $analysis->getKey(),
        'message' => $analysis->getAttribute('error'),
      ], JsonResponse::HTTP_BAD_GATEWAY);
    }

    return response()->json([
      'id' => $analysis->getKey(),
    ], JsonResponse::HTTP_CREATED);
  }

  public function show(int $id): JsonResponse
  {
    $analysis = Analysis::findOrFail($id);
    $analysisResource = new AnalysisResource($analysis);

    return $analysisResource
      ->response();
  }
}
