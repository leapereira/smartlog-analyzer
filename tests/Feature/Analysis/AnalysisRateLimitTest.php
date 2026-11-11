<?php

use App\Models\Log;
use App\Providers\AppServiceProvider;
use Illuminate\Cache\RateLimiter as CacheRateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
  config([
    'services.smartlog.api_key' => 'test-secret',
    'services.smartlog.llm_driver' => 'mock',
    'services.smartlog.api_rate_limit_per_minute' => 10,
    'services.smartlog.log_analysis_rate_limit_per_minute' => 1,
  ]);

  RateLimiter::swap(new CacheRateLimiter(Cache::store('array')));
  (new AppServiceProvider(app()))->boot();
});

it('limita los análisis autenticados y permite consultar el resultado', function () {
  $log = Log::create([
    'service' => 'payments',
    'level' => 'ERROR',
    'message' => 'Payment gateway timed out',
    'timestamp' => '2026-11-10T12:00:00Z',
  ]);
  $payload = ['log_ids' => [$log->getKey()]];

  $this->withHeader('Authorization', 'Bearer wrong-secret')
    ->postJson('/api/v1/logs/analyze', $payload)
    ->assertUnauthorized();

  $this->withHeader('Authorization', 'Bearer test-secret');

  $response = $this->postJson('/api/v1/logs/analyze', $payload)
    ->assertCreated();

  $this->postJson('/api/v1/logs/analyze', $payload)
    ->assertStatus(429)
    ->assertHeader('Retry-After');

  $this->getJson('/api/v1/analysis/' . $response->json('id'))
    ->assertOk()
    ->assertJsonPath('data.id', $response->json('id'));
});

it('cuenta un análisis reutilizado para el límite de solicitudes', function () {
  config(['services.smartlog.log_analysis_rate_limit_per_minute' => 2]);
  $log = Log::create([
    'service' => 'payments',
    'level' => 'ERROR',
    'message' => 'Payment gateway timed out',
    'timestamp' => '2026-11-10T12:00:00Z',
  ]);
  $payload = ['log_ids' => [$log->getKey()]];
  $this->withHeader('Authorization', 'Bearer test-secret');

  $analysisId = $this->postJson('/api/v1/logs/analyze', $payload)
    ->assertCreated()->json('id');
  $this->postJson('/api/v1/logs/analyze', $payload)
    ->assertOk()
    ->assertJsonPath('id', $analysisId);
  $this->postJson('/api/v1/logs/analyze', $payload)
    ->assertStatus(429)
    ->assertHeader('Retry-After');
});
