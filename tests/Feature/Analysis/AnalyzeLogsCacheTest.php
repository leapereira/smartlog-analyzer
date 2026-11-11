<?php

use App\Cache\AnalysisCache;
use App\Contracts\LogAnalyzer;
use App\Models\Analysis;
use App\Models\Log;
use App\Providers\AppServiceProvider;
use Illuminate\Cache\RateLimiter as CacheRateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

$createLog = fn() => Log::create([
  'service' => 'payments',
  'level' => 'ERROR',
  'message' => 'Payment gateway timed out',
  'timestamp' => '2026-11-10T12:00:00Z',
]);

beforeEach(function () {
  config([
    'cache.default' => 'array',
    'services.smartlog.api_key' => 'test-secret',
    'services.smartlog.llm_driver' => 'mock',
  ]);

  RateLimiter::swap(new CacheRateLimiter(Cache::store('array')));
  (new AppServiceProvider(app()))->boot();
  $this->withHeader('Authorization', 'Bearer test-secret');
});

it('reutiliza el análisis aunque los IDs lleguen en otro orden', function () use ($createLog) {
  $first = $createLog();
  $second = $createLog();
  $analysisCount = Analysis::count();
  $analyzer = Mockery::mock(LogAnalyzer::class);
  $analyzer->shouldReceive('analyze')->once()->andReturn([
    'summary' => 'Sin problemas.',
    'categories' => [],
    'suggestions' => [],
  ]);
  app()->instance(LogAnalyzer::class, $analyzer);

  $createdId = $this->postJson('/api/v1/logs/analyze', [
    'log_ids' => [$first->getKey(), $second->getKey()],
  ])->assertCreated()->json('id');

  $reusedId = $this->postJson('/api/v1/logs/analyze', [
    'log_ids' => [$second->getKey(), $first->getKey()],
  ])->assertOk()->json('id');

  expect($reusedId)->toBe($createdId)
    ->and(Analysis::count())->toBe($analysisCount + 1);
});

it('genera otro análisis con cache=false y luego reutiliza el nuevo', function () use ($createLog) {
  $log = $createLog();
  $payload = ['log_ids' => [$log->getKey()]];

  $originalId = $this->postJson('/api/v1/logs/analyze', $payload)
    ->assertCreated()->json('id');
  $newId = $this->postJson('/api/v1/logs/analyze?cache=false', $payload)
    ->assertCreated()->json('id');
  $reusedId = $this->postJson('/api/v1/logs/analyze', $payload)
    ->assertOk()->json('id');

  expect($newId)->not->toBe($originalId)
    ->and($reusedId)->toBe($newId);
});

it('conserva el análisis anterior si una ejecución forzada falla', function () use ($createLog) {
  $log = $createLog();
  $payload = ['log_ids' => [$log->getKey()]];
  $originalId = $this->postJson('/api/v1/logs/analyze', $payload)
    ->assertCreated()->json('id');

  $analyzer = Mockery::mock(LogAnalyzer::class);
  $analyzer->shouldReceive('analyze')->once()->andThrow(new RuntimeException('Fallo simulado.'));
  app()->instance(LogAnalyzer::class, $analyzer);

  $this->postJson('/api/v1/logs/analyze?cache=false', $payload)
    ->assertStatus(502);

  $reusedId = $this->postJson('/api/v1/logs/analyze', $payload)
    ->assertOk()->json('id');

  expect($reusedId)->toBe($originalId);
});

it('devuelve 409 sin crear un análisis si el bloqueo está ocupado', function () use ($createLog) {
  $log = $createLog();
  $cache = app(AnalysisCache::class);
  $lock = $cache->lock($cache->keyFor([$log->getKey()]));
  $analysisCount = Analysis::count();
  expect($lock->get())->toBeTrue();

  try {
    $this->postJson('/api/v1/logs/analyze', [
      'log_ids' => [$log->getKey()],
    ])->assertConflict()
      ->assertJsonPath('message', 'Ya hay un análisis en curso para estos logs. Reintentá en unos segundos.');

    expect(Analysis::count())->toBe($analysisCount);
  } finally {
    $lock->release();
  }
});

it('libera el bloqueo cuando falla el analizador', function () use ($createLog) {
  $log = $createLog();
  $analyzer = Mockery::mock(LogAnalyzer::class);
  $analyzer->shouldReceive('analyze')->once()->andThrow(new RuntimeException('Fallo simulado.'));
  app()->instance(LogAnalyzer::class, $analyzer);

  $this->postJson('/api/v1/logs/analyze', [
    'log_ids' => [$log->getKey()],
  ])->assertStatus(502);

  $cache = app(AnalysisCache::class);
  $lock = $cache->lock($cache->keyFor([$log->getKey()]));
  expect($lock->get())->toBeTrue();
  $lock->release();
});
