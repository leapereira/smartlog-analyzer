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

it('envía los logs elegidos en orden cronológico y con sus conteos', function () use ($createLog, $validResult) {
  $noon = $createLog([
    'message' => 'Primer evento del mediodía',
    'timestamp' => '2026-11-10T09:00:00-03:00',
  ]);
  $morning = $createLog([
    'service' => 'search',
    'level' => 'INFO',
    'message' => 'Evento de la mañana',
    'timestamp' => '2026-11-10T11:00:00Z',
  ]);
  $last = $createLog();
  $createLog(['message' => 'Log no seleccionado']);

  $receivedLogs = [];
  $receivedCounts = [];
  $analyzer = Mockery::mock(LogAnalyzer::class);
  $analyzer->shouldReceive('analyze')->once()->andReturnUsing(
    function (array $logs, array $counts) use (&$receivedLogs, &$receivedCounts, $validResult) {
      $receivedLogs = $logs;
      $receivedCounts = $counts;

      return $validResult;
    },
  );
  app()->instance(LogAnalyzer::class, $analyzer);

  app(AnalyzeLogs::class)->execute([$last->getKey(), $noon->getKey(), $morning->getKey()]);

  expect(array_column($receivedLogs, 'id'))->toBe([
    $morning->getKey(),
    $noon->getKey(),
    $last->getKey(),
  ])->and($receivedLogs[1])->toBe([
    'id' => $noon->getKey(),
    'service' => 'payments',
    'level' => 'ERROR',
    'message' => 'Primer evento del mediodía',
    'timestamp' => '2026-11-10T12:00:00.000000Z',
  ])->and($receivedCounts)->toBe([
    'by_service' => [
      ['service' => 'payments', 'total' => 2],
      ['service' => 'search', 'total' => 1],
    ],
    'by_level' => [
      ['level' => 'ERROR', 'total' => 2],
      ['level' => 'INFO', 'total' => 1],
    ],
  ]);
});

it('guarda el intento y cierra su transacción antes de analizar', function () use ($createLog, $validResult) {
  $log = $createLog();
  // DatabaseTransactions mantiene abierta su propia transacción durante el test.
  $transactionLevel = DB::transactionLevel();
  $stateDuringAnalysis = [];
  $analyzer = Mockery::mock(LogAnalyzer::class);
  $analyzer->shouldReceive('analyze')->once()->andReturnUsing(
    function () use (&$stateDuringAnalysis, $validResult) {
      $analysis = Analysis::query()->latest('id')->firstOrFail();
      $stateDuringAnalysis = [
        'status' => $analysis->status,
        'result' => $analysis->result,
        'error' => $analysis->error,
        'log_ids' => $analysis->logs()->get()->modelKeys(),
        'transaction_level' => DB::transactionLevel(),
      ];

      return $validResult;
    },
  );
  app()->instance(LogAnalyzer::class, $analyzer);

  app(AnalyzeLogs::class)->execute([$log->getKey()]);

  expect($stateDuringAnalysis)->toBe([
    'status' => AnalysisStatusEnum::Processing,
    'result' => null,
    'error' => null,
    'log_ids' => [$log->getKey()],
    'transaction_level' => $transactionLevel,
  ]);
});

it('rechaza un ID inexistente sin crear un intento', function () use ($createLog) {
  $log = $createLog();
  $missingId = $log->getKey();
  $log->delete();
  $analysisCount = Analysis::count();

  $analyzer = Mockery::mock(LogAnalyzer::class);
  $analyzer->shouldNotReceive('analyze');
  app()->instance(LogAnalyzer::class, $analyzer);

  expect(fn() => app(AnalyzeLogs::class)->execute([$missingId]))
    ->toThrow(ValidationException::class);

  expect(Analysis::count())->toBe($analysisCount);
});

it('revierte el intento si falla la asociación de los logs', function () use ($createLog) {
  $log = $createLog();
  $analysisCount = Analysis::count();
  $relationCount = DB::table('analysis_logs')->count();
  $countsQuery = Mockery::mock(LogCountsQuery::class);
  $countsQuery->shouldReceive('execute')->once()->andReturnUsing(function () use ($log) {
    // Simula un borrado entre la lectura del log y su asociación al análisis.
    $log->delete();

    return ['by_service' => [], 'by_level' => []];
  });
  $analyzer = Mockery::mock(LogAnalyzer::class);
  $analyzer->shouldNotReceive('analyze');
  app()->instance(LogCountsQuery::class, $countsQuery);
  app()->instance(LogAnalyzer::class, $analyzer);

  expect(fn() => app(AnalyzeLogs::class)->execute([$log->getKey()]))
    ->toThrow(QueryException::class);

  expect(Analysis::count())->toBe($analysisCount)
    ->and(DB::table('analysis_logs')->count())->toBe($relationCount);
});
