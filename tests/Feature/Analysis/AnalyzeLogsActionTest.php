<?php

use App\Actions\AnalyzeLogs;
use App\Contracts\LogAnalyzer;
use App\Enums\AnalysisStatusEnum;
use App\Models\Analysis;
use App\Models\Log;
use App\Queries\LogCountsQuery;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

$createLog = fn(array $attributes = []) => Log::create([
  'service' => 'payments',
  'level' => 'ERROR',
  'message' => 'Payment gateway timed out',
  'timestamp' => '2026-11-10T12:00:00Z',
  ...$attributes,
]);

$validResult = [
  'summary' => 'Los logs indican un tiempo de espera agotado.',
  'categories' => ['timeout'],
  'suggestions' => ['Revisar la latencia del proveedor.'],
];

it('guarda el análisis completado con su resultado y los logs seleccionados', function () use ($createLog, $validResult) {
  $first = $createLog();
  $second = $createLog();
  $selectedIds = [$first->getKey(), $second->getKey()];

  // Usamos un resultado conocido para comprobar cómo se guarda el análisis
  $analyzer = Mockery::mock(LogAnalyzer::class);
  $analyzer->shouldReceive('analyze')->once()->andReturn($validResult);
  app()->instance(LogAnalyzer::class, $analyzer);

  $analysis = app(AnalyzeLogs::class)->execute($selectedIds)->refresh();

  expect($analysis->getAttribute('status'))->toBe(AnalysisStatusEnum::Completed)
    ->and($analysis->getAttribute('result'))->toBe($validResult)
    ->and($analysis->getAttribute('error'))->toBeNull()
    ->and($analysis->logs()->orderBy('logs.id')->get()->modelKeys())->toBe($selectedIds);
});

it('envía solo los logs elegidos en orden cronológico y con sus conteos', function () use ($createLog, $validResult) {
  // El primer y el último evento ocurren a la misma hora, el ID debe desempatar
  $first = $createLog([
    'message' => 'Primer evento',
    'timestamp' => '2026-11-10T09:00:00-03:00',
  ]);

  $earlier = $createLog([
    'service' => 'search',
    'level' => 'INFO',
    'message' => 'Evento anterior',
    'timestamp' => '2026-11-10T11:00:00Z',
  ]);

  $last = $createLog(['message' => 'Último evento']);
  // Este log debe quedar fuera de los datos enviados y de los conteos
  $createLog(['message' => 'Evento excluido']);
  $analyzer = Mockery::mock(LogAnalyzer::class);
  $analyzer->shouldReceive('analyze')->once()->andReturn($validResult);
  app()->instance(LogAnalyzer::class, $analyzer);

  // Enviamos los IDs desordenados para comprobar que la acción los ordena
  app(AnalyzeLogs::class)->execute([
    $last->getKey(),
    $first->getKey(),
    $earlier->getKey(),
  ]);

  $expectedLogs = [
    [
      'id' => $earlier->getKey(),
      'service' => 'search',
      'level' => 'INFO',
      'message' => 'Evento anterior',
      'timestamp' => '2026-11-10T11:00:00.000000Z',
    ],
    [
      'id' => $first->getKey(),
      'service' => 'payments',
      'level' => 'ERROR',
      'message' => 'Primer evento',
      'timestamp' => '2026-11-10T12:00:00.000000Z',
    ],
    [
      'id' => $last->getKey(),
      'service' => 'payments',
      'level' => 'ERROR',
      'message' => 'Último evento',
      'timestamp' => '2026-11-10T12:00:00.000000Z',
    ],
  ];
  $expectedCounts = [
    'by_service' => [
      ['service' => 'payments', 'total' => 2],
      ['service' => 'search', 'total' => 1],
    ],
    'by_level' => [
      ['level' => 'ERROR', 'total' => 2],
      ['level' => 'INFO', 'total' => 1],
    ],
  ];

  $analyzer->shouldHaveReceived('analyze')->with($expectedLogs, $expectedCounts)->once();
});

it('prepara el intento antes de llamar al analizador y cierra su transacción', function () use ($createLog, $validResult) {
  $log = $createLog();
  // DatabaseTransactions ya abrió una transacción, la acción debe cerrar solo la suya
  $transactionLevel = DB::transactionLevel();
  $observedState = [];
  $analyzer = Mockery::mock(LogAnalyzer::class);

  $analyzer
    ->shouldReceive('analyze')
    ->once()
    ->andReturnUsing(function () use (&$observedState, $validResult) {
      // Capturamos el estado durante la llamada, antes de que el análisis termine
      $attempt = Analysis::query()->latest('id')->firstOrFail();
      $observedState = [
        'status' => $attempt->getAttribute('status'),
        'result' => $attempt->getAttribute('result'),
        'error' => $attempt->getAttribute('error'),
        'log_ids' => $attempt->logs()->get()->modelKeys(),
        'transaction_level' => DB::transactionLevel(),
      ];

      return $validResult;
    });
  app()->instance(LogAnalyzer::class, $analyzer);

  app(AnalyzeLogs::class)->execute([$log->getKey()]);

  expect($observedState)->toBe([
    'status' => AnalysisStatusEnum::Processing,
    'result' => null,
    'error' => null,
    'log_ids' => [$log->getKey()],
    'transaction_level' => $transactionLevel,
  ]);
});

it('rechaza selecciones inválidas sin crear intentos ni llamar al analizador', function (string $selection) use ($createLog) {
  $log = $createLog();
  $missing = $createLog();
  $missingId = $missing->getKey();
  // Conservamos un ID válido donde el log ya no existe
  $missing->delete();

  $ids = match ($selection) {
    'vacía' => [],
    'duplicada' => [$log->getKey(), $log->getKey()],
    'inexistente' => [$log->getKey(), $missingId],
  };
  $analysisCount = Analysis::query()->count();
  $relationCount = DB::table('analysis_logs')->count();

  $analyzer = Mockery::mock(LogAnalyzer::class);
  $analyzer->shouldNotReceive('analyze');
  app()->instance(LogAnalyzer::class, $analyzer);

  expect(fn() => app(AnalyzeLogs::class)->execute($ids))
    ->toThrow(function (ValidationException $exception) {
      expect($exception->status)->toBe(422)
        ->and($exception->errors())->toHaveKey('log_ids');
    });

  // Rechazar la selección no debe dejar intentos ni relaciones nuevas
  expect(Analysis::query()->count())->toBe($analysisCount)
    ->and(DB::table('analysis_logs')->count())->toBe($relationCount);
})->with(['vacía', 'duplicada', 'inexistente']);

it('conserva el intento y sus relaciones cuando el proveedor lanza una excepción', function () use ($createLog) {
  $log = $createLog();
  $error = 'El proveedor no respondió.';
  $analyzer = Mockery::mock(LogAnalyzer::class);
  // El proveedor falla después de que la acción haya guardado el intento
  $analyzer->shouldReceive('analyze')->once()->andThrow(new RuntimeException($error));
  app()->instance(LogAnalyzer::class, $analyzer);

  $analysis = app(AnalyzeLogs::class)->execute([$log->getKey()])->refresh();

  expect($analysis->getAttribute('status'))->toBe(AnalysisStatusEnum::Failed)
    ->and($analysis->getAttribute('result'))->toBeNull()
    ->and($analysis->getAttribute('error'))->toBe($error)
    ->and($analysis->logs()->get()->modelKeys())->toBe([$log->getKey()]);
});

it('conserva el intento y sus relaciones cuando el resultado es inválido', function () use ($createLog) {
  $log = $createLog();
  $analyzer = Mockery::mock(LogAnalyzer::class);
  // La respuesta incumple el contrato: summary debe ser texto
  $analyzer->shouldReceive('analyze')->once()->andReturn(['summary' => 7]);
  app()->instance(LogAnalyzer::class, $analyzer);

  $analysis = app(AnalyzeLogs::class)->execute([$log->getKey()])->refresh();

  expect($analysis->getAttribute('status'))->toBe(AnalysisStatusEnum::Failed)
    ->and($analysis->getAttribute('result'))->toBeNull()
    ->and($analysis->getAttribute('error'))->toBe('El resumen del análisis debe ser un texto.')
    ->and($analysis->logs()->get()->modelKeys())->toBe([$log->getKey()]);
});

it('revierte el intento si falla la asociación de los logs', function () use ($createLog) {
  $log = $createLog();
  $analysisCount = Analysis::query()->count();
  $relationCount = DB::table('analysis_logs')->count();
  $countsQuery = Mockery::mock(LogCountsQuery::class);
  $countsQuery->shouldReceive('execute')->once()->andReturnUsing(function () use ($log) {
    // Simula un borrado entre la lectura de los logs y la asociación al análisis
    $log->delete();

    return ['by_service' => [], 'by_level' => []];
  });
  $analyzer = Mockery::mock(LogAnalyzer::class);
  $analyzer->shouldNotReceive('analyze');
  app()->instance(LogCountsQuery::class, $countsQuery);
  app()->instance(LogAnalyzer::class, $analyzer);

  expect(fn() => app(AnalyzeLogs::class)->execute([$log->getKey()]))
    ->toThrow(QueryException::class);

  // El rollback debe deshacer tanto el intento como sus relaciones.
  expect(Analysis::query()->count())->toBe($analysisCount)
    ->and(DB::table('analysis_logs')->count())->toBe($relationCount);
});
