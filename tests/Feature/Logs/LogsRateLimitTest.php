<?php

use Illuminate\Cache\RateLimiter as CacheRateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
  config(['services.smartlog.api_key' => 'test-secret']);

  RateLimiter::swap(new CacheRateLimiter(Cache::store('array')));

  RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(2)->by($request->ip());
  });
});

it('comparte el límite entre GET y POST y cuenta las claves incorrectas', function () {
  $this->withHeader('Authorization', 'Bearer wrong-secret')
    ->getJson('/api/v1/logs')
    ->assertUnauthorized();

  $this->withHeader('Authorization', 'Bearer test-secret')
    ->postJson('/api/v1/logs', [
      'service' => 'payments',
      'level' => 'ERROR',
      'message' => 'Payment gateway timed out',
      'timestamp' => '2026-11-10T12:30:00Z',
    ])
    ->assertCreated();

  $this->getJson('/api/v1/logs')
    ->assertStatus(429)
    ->assertHeader('Retry-After');
});

it('permite consultar health con una clave válida aunque se agote el límite', function () {
  $this->withHeader('Authorization', 'Bearer wrong-secret');

  $this->getJson('/api/v1/logs')->assertUnauthorized();
  $this->getJson('/api/v1/logs')->assertUnauthorized();
  $this->getJson('/api/v1/logs')->assertStatus(429);

  DB::shouldReceive('select')
    ->once()
    ->with('SELECT 1')
    ->andReturn([(object) ['?column?' => 1]]);

  Redis::shouldReceive('ping')
    ->once()
    ->andReturn('PONG');

  $this->getJson('/api/v1/health')->assertUnauthorized();

  $this->withHeader('Authorization', 'Bearer test-secret')
    ->getJson('/api/v1/health')
    ->assertOk()
    ->assertExactJson(['status' => 'ok']);
});
