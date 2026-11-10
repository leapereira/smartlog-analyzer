<?php

use App\Models\Analysis;
use App\Models\Log;
use Illuminate\Database\QueryException;

function createAnalysisWithLog(): array
{
  $log = Log::create([
    'service' => 'payments',
    'level' => 'ERROR',
    'message' => 'Payment gateway timed out',
    'timestamp' => '2026-11-10T12:00:00+00:00',
  ]);

  $analysis = Analysis::create();
  $analysis->logs()->attach($log->getKey());

  return [$analysis, $log];
}

it('permite usar el mismo log en dos análisis', function () {
  [$analysis, $log] = createAnalysisWithLog();

  $secondAnalysis = Analysis::create();
  $secondAnalysis->logs()->attach($log->getKey());

  $analysisIds = $log->analyses()
    ->orderBy('analyses.id')
    ->get()
    ->modelKeys();

  expect($analysisIds)
    ->toBe([
      $analysis->getKey(),
      $secondAnalysis->getKey()
    ]);
  expect($secondAnalysis->logs()->get()->modelKeys())
    ->toBe([$log->getKey()]);
});

it('rechaza asociar el mismo log dos veces al mismo análisis', function () {
  [$analysis, $log] = createAnalysisWithLog();

  $this->expectException(QueryException::class);
  $this->expectExceptionCode('23505'); // Valor duplicado (unique_violation)

  $analysis->logs()->attach($log->getKey());
});

it('rechaza borrar un log que pertenece a un análisis', function () {
  [, $log] = createAnalysisWithLog();

  $this->expectException(QueryException::class);
  $this->expectExceptionCode('23001'); // Borrado restringido (restrict_violation)

  $log->delete();
});
