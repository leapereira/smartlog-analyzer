<?php

use App\Analyzers\AnalysisResult;
use App\Analyzers\MockLogAnalyzer;

$logs = [
  [
    'id' => 7,
    'service' => 'payments',
    'level' => 'ERROR',
    'message' => 'No se pudo procesar el pago.',
    'timestamp' => '2026-11-10T12:30:00Z',
  ],
  [
    'id' => 9,
    'service' => 'payments',
    'level' => 'ERROR',
    'message' => 'La operación fue rechazada.',
    'timestamp' => '2026-11-10T12:31:00Z',
  ],
];

$counts = [
  'by_service' => [['service' => 'payments', 'total' => 2]],
  'by_level' => [['level' => 'ERROR', 'total' => 2]],
];

it('detecta los patrones y devuelve un resultado válido', function (
  string $message,
  string $category,
  string $label,
  string $suggestion,
) use ($logs, $counts) {
  $logs[0]['message'] = $message;

  $result = (new MockLogAnalyzer())->analyze($logs, $counts);

  expect($result['categories'])->toBe([$category])
    ->and($result['suggestions'])->toBe([$suggestion])
    ->and($result['summary'])->toEndWith(" {$label} en payments (1).")
    ->and(AnalysisResult::fromArray($result)->toArray())->toBe($result);
})->with([
  'timeout en mayúsculas' => [
    'Payment gateway REQUEST TIMEOUT',
    'timeout',
    'Timeouts',
    'Revisar la latencia y los tiempos de espera de las dependencias.',
  ],
  'timed out' => [
    'Payment gateway timed out',
    'timeout',
    'Timeouts',
    'Revisar la latencia y los tiempos de espera de las dependencias.',
  ],
  'límite de solicitudes' => [
    'Provider returned status=429',
    'rate_limit',
    'Límites de solicitudes alcanzados',
    'Respetar el tiempo de reintento y reducir la frecuencia de solicitudes al proveedor.',
  ],
  'fallo de conexión' => [
    'Database connection refused',
    'connection_failure',
    'Fallos de conexión',
    'Verificar la disponibilidad de la dependencia y las conexiones de su pool.',
  ],
  'dependencia no disponible' => [
    'Provider returned SERVICE UNAVAILABLE',
    'dependency_unavailable',
    'Dependencias no disponibles',
    'Comprobar el estado de la dependencia antes de reintentar.',
  ],
]);

it('deja las categorías y sugerencias vacías si no encuentra patrones', function () use ($logs, $counts) {
  $result = (new MockLogAnalyzer())->analyze($logs, $counts);

  expect($result['summary'])->toBe(
    'Se analizaron 2 logs. Los servicios fueron payments (2). Los niveles fueron ERROR (2).',
  )
    ->and($result['categories'])->toBe([])
    ->and($result['suggestions'])->toBe([]);
});

it('incluye los conteos de cada servicio y nivel en el resumen', function () use ($logs, $counts) {
  $logs[1]['service'] = 'search';
  $logs[1]['level'] = 'INFO';
  $counts = [
    'by_service' => [
      ['service' => 'payments', 'total' => 1],
      ['service' => 'search', 'total' => 1],
    ],
    'by_level' => [
      ['level' => 'ERROR', 'total' => 1],
      ['level' => 'INFO', 'total' => 1],
    ],
  ];

  $result = (new MockLogAnalyzer())->analyze($logs, $counts);

  expect($result['summary'])->toBe(
    'Se analizaron 2 logs. Los servicios fueron payments (1), search (1). Los niveles fueron ERROR (1), INFO (1).',
  );
});

it('permite varias categorías por log y conserva el orden de los patrones', function () use ($logs, $counts) {
  $logs[0]['message'] = 'Service unavailable: connection refused';
  $logs[1]['message'] = 'status=429; request timeout';

  $result = (new MockLogAnalyzer())->analyze($logs, $counts);

  expect($result['categories'])->toBe([
    'timeout',
    'rate_limit',
    'connection_failure',
    'dependency_unavailable',
  ])
    ->and($result['suggestions'])->toHaveCount(4)
    ->and($result['summary'])->toEndWith(
      ' Timeouts en payments (1). Límites de solicitudes alcanzados en payments (1). Fallos de conexión en payments (1). Dependencias no disponibles en payments (1).',
    );
});

it('cuenta cada log una sola vez por categoría y agrupa por servicio', function () use ($logs, $counts) {
  $logs[0]['message'] = 'request timeout; connection timeout';
  $logs[1]['message'] = 'Payment gateway timed out';
  $logs[] = [
    ...$logs[1],
    'id' => 10,
    'service' => 'search',
    'message' => 'Search exceeded deadline',
  ];
  $counts = [
    'by_service' => [
      ['service' => 'payments', 'total' => 2],
      ['service' => 'search', 'total' => 1],
    ],
    'by_level' => [['level' => 'ERROR', 'total' => 3]],
  ];

  $result = (new MockLogAnalyzer())->analyze($logs, $counts);

  expect($result['categories'])->toBe(['timeout'])
    ->and($result['suggestions'])->toHaveCount(1)
    ->and($result['summary'])->toEndWith(' Timeouts en payments (2), search (1).');
});

it('produce el mismo resultado para la misma selección', function () use ($logs, $counts) {
  $logs[0]['message'] = 'request timeout; status=429';
  $logs[1]['message'] = 'connection refused';
  $analyzer = new MockLogAnalyzer();

  expect($analyzer->analyze($logs, $counts))
    ->toBe($analyzer->analyze($logs, $counts));
});
