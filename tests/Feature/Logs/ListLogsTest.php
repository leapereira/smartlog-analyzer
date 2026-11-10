<?php

use App\Models\Log;
use Illuminate\Support\Str;

beforeEach(function () {
  config(['services.smartlog.api_key' => 'test-secret']);

  $this->withHeader('Authorization', 'Bearer test-secret');
});

it('aplica los filtros al listado y al resumen', function (
  ?string $level,
  int $expectedTotal,
  array $expectedCountsByLevel,
) {
  $service = 'test-' . Str::uuid();

  $examples = [
    [$service, 'ERROR', '2026-11-10T12:00:00Z', 'Error reciente'],
    [$service, 'INFO', '2026-11-10T11:00:00Z', 'Evento informativo'],
    [$service, 'ERROR', '2026-11-10T09:00:00Z', 'Error anterior'],
    [$service, 'ERROR', '2026-11-09T12:00:00Z', 'Antes del rango'],
    [$service, 'ERROR', '2026-11-11T12:00:00Z', 'Después del rango'],
    [$service . '-otro', 'ERROR', '2026-11-10T12:00:00Z', 'Otro servicio'],
  ];

  foreach ($examples as [$logService, $logLevel, $timestamp, $message]) {
    Log::create([
      'service' => $logService,
      'level' => $logLevel,
      'message' => $message,
      'timestamp' => $timestamp,
    ]);
  }

  $query = http_build_query([
    'service' => $service,
    'level' => $level,
    'from' => '2026-11-10T09:00:00Z',
    'to' => '2026-11-10T12:00:00Z',
    'per_page' => 1,
  ]);

  $response = $this->getJson('/api/v1/logs?' . $query);

  $response->assertOk()
    ->assertJsonCount(1, 'logs')
    ->assertJsonPath('logs.0.service', $service)
    ->assertJsonPath('logs.0.message', 'Error reciente')
    ->assertJsonPath('pagination.total', $expectedTotal)
    ->assertJsonCount(1, 'summary.by_service')
    ->assertJsonPath('summary.by_service.0.service', $service)
    ->assertJsonPath('summary.by_service.0.total', $expectedTotal);

  $countsByLevel = collect($response->json('summary.by_level'))
    ->pluck('total', 'level')
    ->sortKeys()
    ->all();

  expect($countsByLevel)->toBe($expectedCountsByLevel);
})->with([
  'todos los niveles' => [null, 3, ['ERROR' => 2, 'INFO' => 1]],
  'solo errores' => ['ERROR', 2, ['ERROR' => 2]],
]);

it('devuelve un listado y un resumen vacíos cuando no hay coincidencias', function () {
  $query = http_build_query(['service' => 'test-' . Str::uuid()]);

  $this->getJson('/api/v1/logs?' . $query)
    ->assertOk()
    ->assertJsonPath('logs', [])
    ->assertJsonPath('summary.by_service', [])
    ->assertJsonPath('summary.by_level', [])
    ->assertJsonPath('pagination.total', 0);
});

it('rechaza un rango de fechas invertido', function () {
  $query = http_build_query([
    'from' => '2026-11-11T00:00:00Z',
    'to' => '2026-11-10T00:00:00Z',
  ]);

  $this->getJson('/api/v1/logs?' . $query)
    ->assertUnprocessable()
    ->assertJsonValidationErrors(['to']);
});
