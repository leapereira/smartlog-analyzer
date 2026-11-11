<?php

namespace App\Cache;

use App\Enums\AnalysisStatusEnum;
use App\Models\Analysis;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class AnalysisCache
{
  // Incrementar al cambiar las instrucciones, el formato o el comportamiento del analizador
  private const VERSION = 1;

  public function keyFor(array $logIds): string
  {
    sort($logIds, SORT_NUMERIC);
    $driver = config('services.smartlog.llm_driver');

    $data = [
      'log_ids' => $logIds,
      'driver' => $driver,
      'version' => self::VERSION,
    ];

    if ($driver === 'openai') {
      $data['model'] = config('services.openai.model');
      $data['max_output_tokens'] = config('services.openai.max_output_tokens');
    }

    return 'smartlog:analysis:' . hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
  }

  public function findCompleted(string $key): ?Analysis
  {
    $analysisId = Cache::get($key);

    if ($analysisId === null) {
      return null;
    }

    if (!ctype_digit((string) $analysisId)) {
      Cache::forget($key);

      return null;
    }

    $analysis = Analysis::find($analysisId);

    if ($analysis?->status === AnalysisStatusEnum::Completed) {
      return $analysis;
    }

    Cache::forget($key);

    return null;
  }

  public function storeCompleted(string $key, Analysis $analysis): void
  {
    if (!Cache::put($key, $analysis->getKey(), config('services.smartlog.analysis_cache_ttl'))) {
      throw new RuntimeException('No se pudo publicar el análisis en caché.');
    }
  }

  public function lock(string $key): Lock
  {
    return Cache::lock($key . ':lock', config('services.smartlog.analysis_lock_seconds'));
  }
}
