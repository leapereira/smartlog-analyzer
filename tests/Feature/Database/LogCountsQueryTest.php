<?php

use App\Models\Log;
use App\Queries\LogCountsQuery;
use Illuminate\Support\Str;

it('cuenta solo los logs seleccionados sin modificar la consulta original', function () {
  $firstService = 'a-test-' . Str::uuid();
  $secondService = 'b-test-' . Str::uuid();
  $logs = [];

  foreach (
    [
      [$firstService, 'ERROR', '2026-11-10T12:00:00Z'],
      [$secondService, 'INFO', '2026-11-10T12:00:00Z'],
      [$firstService, 'ERROR', '2026-11-10T11:00:00Z'],
      [$firstService, 'ERROR', '2026-11-10T13:00:00Z'],
    ] as [$service, $level, $timestamp]
  ) {
    $logs[] = Log::create([
      'service' => $service,
      'level' => $level,
      'message' => 'Evento de prueba para los conteos.',
      'timestamp' => $timestamp,
    ]);
  }

  $query = Log::query()
    ->whereKey([$logs[0]->getKey(), $logs[1]->getKey(), $logs[2]->getKey()])
    ->orderByDesc('timestamp')
    ->orderByDesc('id');

  $counts = app(LogCountsQuery::class)->execute($query);

  expect($counts)->toBe([
    'by_service' => [
      ['service' => $firstService, 'total' => 2],
      ['service' => $secondService, 'total' => 1],
    ],
    'by_level' => [
      ['level' => 'ERROR', 'total' => 2],
      ['level' => 'INFO', 'total' => 1],
    ],
  ])
    ->and($query->get()->modelKeys())
    ->toBe([
      $logs[1]->getKey(),
      $logs[0]->getKey(),
      $logs[2]->getKey(),
    ]);
});
