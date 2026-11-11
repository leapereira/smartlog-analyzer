<?php

use App\Cache\AnalysisCache;
use App\Models\Log;
use App\Providers\AppServiceProvider;
use Illuminate\Cache\RateLimiter as CacheRateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

beforeEach(function () {
  config([
    'cache.default' => 'redis',
    'cache.prefix' => 'smartlog-analysis-test:' . Str::uuid() . ':',
    'services.smartlog.api_key' => 'test-secret',
    'services.smartlog.llm_driver' => 'mock',
  ]);

  Cache::purge('redis');
  RateLimiter::swap(new CacheRateLimiter(Cache::store('array')));
  (new AppServiceProvider(app()))->boot();
  $this->withHeader('Authorization', 'Bearer test-secret');
});

it('permite adquirir un bloqueo de Redis a una sola solicitud por vez', function () {
  $cache = app(AnalysisCache::class);
  $key = 'smartlog:test:lock:' . Str::uuid();
  $first = $cache->lock($key);
  $second = $cache->lock($key);

  try {
    expect($first->get())->toBeTrue()
      ->and($second->get())->toBeFalse();

    expect($first->release())->toBeTrue()
      ->and($second->get())->toBeTrue();
  } finally {
    $first->forceRelease();
  }
});

it('reutiliza y reemplaza la referencia del análisis con Redis real', function () {
  $log = Log::create([
    'service' => 'payments',
    'level' => 'ERROR',
    'message' => 'Payment gateway timed out',
    'timestamp' => '2026-11-10T12:00:00Z',
  ]);
  $payload = ['log_ids' => [$log->getKey()]];
  $cache = app(AnalysisCache::class);
  $key = $cache->keyFor($payload['log_ids']);

  try {
    $createdId = $this->postJson('/api/v1/logs/analyze', $payload)
      ->assertCreated()->json('id');
    $reusedId = $this->postJson('/api/v1/logs/analyze', $payload)
      ->assertOk()->json('id');
    $newId = $this->postJson('/api/v1/logs/analyze?cache=false', $payload)
      ->assertCreated()->json('id');
    $latestId = $this->postJson('/api/v1/logs/analyze', $payload)
      ->assertOk()->json('id');

    expect($reusedId)->toBe($createdId)
      ->and($newId)->not->toBe($createdId)
      ->and($latestId)->toBe($newId)
      ->and((int) Cache::get($key))->toBe($newId);
  } finally {
    Cache::forget($key);
    $cache->lock($key)->forceRelease();
  }
});
