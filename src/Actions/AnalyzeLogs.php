<?php

namespace App\Actions;

use App\Actions\Results\AnalysisExecutionResult;
use App\Analyzers\AnalysisResult;
use App\Cache\AnalysisCache;
use App\Contracts\LogAnalyzer;
use App\Enums\AnalysisStatusEnum;
use App\Exceptions\AnalysisFailedException;
use App\Exceptions\AnalysisInProgressException;
use App\Exceptions\AnalyzerTimeoutException;
use App\Models\Analysis;
use App\Models\Log;
use App\Queries\LogCountsQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class AnalyzeLogs
{
  public function __construct(
    private LogAnalyzer $logAnalyzer,
    private LogCountsQuery $logCountsQuery,
    private AnalysisCache $analysisCache,
  ) {}

  public function execute(array $logIds, bool $useCache = true): AnalysisExecutionResult
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

    $key = $this->analysisCache->keyFor($logIds);

    if ($useCache) {
      $cached = $this->analysisCache->findCompleted($key);

      if ($cached !== null) {
        return new AnalysisExecutionResult($cached, true);
      }
    }

    $lock = $this->analysisCache->lock($key);

    if (!$lock->get()) {
      throw new AnalysisInProgressException();
    }

    try {
      // Otra solicitud pudo completar el análisis mientras esperábamos el bloqueo.
      if ($useCache) {
        $cached = $this->analysisCache->findCompleted($key);

        if ($cached !== null) {
          return new AnalysisExecutionResult($cached, true);
        }
      }

      $analysis = $this->generateAnalysis($logIds, $query, $logs);

      try {
        $this->analysisCache->storeCompleted($key, $analysis);
      } catch (Throwable $exception) {
        logger()->error('No se pudo publicar el análisis en caché.', [
          'analysis_id' => $analysis->getKey(),
          'exception' => $exception,
        ]);
      }

      return new AnalysisExecutionResult($analysis, false);
    } finally {
      try {
        $lock->release();
      } catch (Throwable $exception) {
        logger()->error('No se pudo liberar el bloqueo del análisis.', [
          'exception' => $exception,
        ]);
      }
    }
  }

  private function generateAnalysis(array $logIds, Builder $query, Collection $logs): Analysis
  {
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
      $message = $exception instanceof AnalyzerTimeoutException
        ? 'El analizador superó el tiempo de espera.'
        : 'No se pudo completar el análisis.';

      $analysis->update([
        'status' => AnalysisStatusEnum::Failed,
        'result' => null,
        'error' => $message,
      ]);

      logger()->error('Falló el análisis de logs.', [
        'analysis_id' => $analysis->getKey(),
        'exception' => $exception,
      ]);

      throw new AnalysisFailedException($analysis, $message, $exception);
    }

    $analysis->update([
      'status' => AnalysisStatusEnum::Completed,
      'result' => $result->toArray(),
      'error' => null,
    ]);

    return $analysis;
  }
}
