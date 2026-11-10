<?php

namespace App\Actions;

use App\Analyzers\AnalysisResult;
use App\Contracts\LogAnalyzer;
use App\Enums\AnalysisStatusEnum;
use App\Models\Analysis;
use App\Models\Log;
use App\Queries\LogCountsQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class AnalyzeLogs
{
  public function __construct(
    private LogAnalyzer $logAnalyzer,
    private LogCountsQuery $logCountsQuery,
  ) {}

  public function execute(array $logIds): Analysis
  {
    $query = Log::query()
      ->whereKey($logIds)
      ->orderBy('timestamp')
      ->orderBy('id');
    $logs = $query->get();

    if ($logs->isEmpty() || $logs->count() !== count($logIds)) {
      throw ValidationException::withMessages([
        'log_ids' => 'La selección debe contener logs existentes y sin duplicados.',
      ]);
    }

    $counts = $this->logCountsQuery->execute($query);
    $logsData = $logs->map(fn(Log $log) => [
      'id' => $log->getKey(),
      'service' => $log->service,
      'level' => $log->level,
      'message' => $log->message,
      'timestamp' => $log->timestamp->copy()->utc()->format('Y-m-d\TH:i:s.u\Z'),
    ])->all();

    $analysis = DB::transaction(function () use ($logIds) {
      $analysis = Analysis::create([
        'status' => AnalysisStatusEnum::Processing,
      ]);
      $analysis->logs()->attach($logIds);

      return $analysis;
    });

    try {
      $result = AnalysisResult::fromArray(
        $this->logAnalyzer->analyze($logsData, $counts),
      );
    } catch (Throwable $exception) {
      $analysis->update([
        'status' => AnalysisStatusEnum::Failed,
        'result' => null,
        'error' => $exception->getMessage(),
      ]);

      return $analysis;
    }

    $analysis->update([
      'status' => AnalysisStatusEnum::Completed,
      'result' => $result->toArray(),
      'error' => null,
    ]);

    return $analysis;
  }
}
