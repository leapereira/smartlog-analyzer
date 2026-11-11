<?php

use App\Enums\AnalysisStatusEnum;
use App\Models\Analysis;
use App\Cache\AnalysisCache;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
  config([
    'cache.default' => 'array',
    'services.smartlog.llm_driver' => 'mock',
    'services.smartlog.analysis_cache_ttl' => 3600,
    'services.smartlog.analysis_lock_seconds' => 60,
  ]);
});

it('guarda el ID de un análisis completado y lo recupera desde PostgreSQL', function () {
  $analysis = Analysis::create(['status' => AnalysisStatusEnum::Completed]);
  $cache = app(AnalysisCache::class);
  $key = $cache->keyFor([5, 12]);

  $cache->storeCompleted($key, $analysis);

  expect(Cache::get($key))->toBe($analysis->getKey())
    ->and($cache->findCompleted($key)?->getKey())->toBe($analysis->getKey());
});

it('elimina una referencia si el análisis no está completado', function () {
  $analysis = Analysis::create(['status' => AnalysisStatusEnum::Processing]);
  $cache = app(AnalysisCache::class);
  $key = $cache->keyFor([$analysis->getKey()]);
  Cache::put($key, $analysis->getKey(), 3600);

  expect($cache->findCompleted($key))->toBeNull()
    ->and(Cache::has($key))->toBeFalse();
});

it('elimina referencias a análisis inexistentes o IDs inválidos', function ($id) {
  $cache = app(AnalysisCache::class);
  $key = $cache->keyFor([5, 12]);
  Cache::put($key, $id, 3600);

  expect($cache->findCompleted($key))->toBeNull()
    ->and(Cache::has($key))->toBeFalse();
})->with([
  'análisis inexistente' => [PHP_INT_MAX],
  'ID inválido' => ['no-es-un-id'],
  'ID negativo' => [-1],
]);

it('vence la referencia sin borrar el análisis de PostgreSQL', function () {
  config(['services.smartlog.analysis_cache_ttl' => 2]);
  $analysis = Analysis::create(['status' => AnalysisStatusEnum::Completed]);
  $cache = app(AnalysisCache::class);
  $key = $cache->keyFor([$analysis->getKey()]);
  $cache->storeCompleted($key, $analysis);

  $this->travel(3)->seconds();

  expect($cache->findCompleted($key))->toBeNull()
    ->and(Analysis::find($analysis->getKey()))->not->toBeNull();
});

it('permite un solo bloqueo a la vez y lo libera', function () {
  $cache = app(AnalysisCache::class);
  $key = $cache->keyFor([5, 12]);
  $first = $cache->lock($key);
  $second = $cache->lock($key);

  expect($first->get())->toBeTrue()
    ->and($second->get())->toBeFalse();

  $first->release();

  expect($second->get())->toBeTrue();
  $second->release();
});
