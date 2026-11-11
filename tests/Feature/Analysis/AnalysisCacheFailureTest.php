<?php

use App\Cache\AnalysisCache;
use App\Enums\AnalysisStatusEnum;
use App\Models\Analysis;
use App\Models\Log;
use App\Providers\AppServiceProvider;
use Illuminate\Cache\RateLimiter as CacheRateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

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

$createLog = fn() => Log::create([
  'service' => 'payments',
  'level' => 'ERROR',
  'message' => 'Payment gateway timed out',
  'timestamp' => '2026-11-10T12:00:00Z',
]);

it('devuelve 503 sin crear un análisis si falla la consulta de caché', function () use ($createLog) {
  $log = $createLog();
  $analysisCount = Analysis::count();
  $cache = Mockery::mock(AnalysisCache::class)->makePartial();
  $cache->shouldReceive('findCompleted')->once()->andThrow(new RedisException('Redis no disponible.'));
  app()->instance(AnalysisCache::class, $cache);

  $this->postJson('/api/v1/logs/analyze', [
    'log_ids' => [$log->getKey()],
  ])->assertServiceUnavailable()
    ->assertJsonPath('message', 'El servicio de caché no está disponible.');

  expect(Analysis::count())->toBe($analysisCount);
});

it('devuelve 503 sin crear un análisis si falla el limitador', function () use ($createLog) {
  $log = $createLog();
  $analysisCount = Analysis::count();
  $limiter = Mockery::mock(CacheRateLimiter::class, [Cache::store('array')])->makePartial();
  $limiter->shouldReceive('tooManyAttempts')->once()->andThrow(new RedisException('Redis no disponible.'));
  RateLimiter::swap($limiter);
  (new AppServiceProvider(app()))->boot();

  $this->postJson('/api/v1/logs/analyze', [
    'log_ids' => [$log->getKey()],
  ])->assertServiceUnavailable()
    ->assertJsonPath('message', 'El servicio de caché no está disponible.');

  expect(Analysis::count())->toBe($analysisCount);
});

it('mantiene completed y devuelve 201 si falla la publicación en caché', function () use ($createLog) {
  $log = $createLog();
  $cache = Mockery::mock(AnalysisCache::class)->makePartial();
  $cache->shouldReceive('storeCompleted')->once()->andThrow(new RedisException('Redis no disponible.'));
  app()->instance(AnalysisCache::class, $cache);

  $response = $this->postJson('/api/v1/logs/analyze', [
    'log_ids' => [$log->getKey()],
  ])->assertCreated();

  expect(Analysis::findOrFail($response->json('id'))->status)
    ->toBe(AnalysisStatusEnum::Completed);
});
