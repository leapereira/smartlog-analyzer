
<?php

use App\Contracts\LogAnalyzer;
use App\Enums\AnalysisStatusEnum;
use App\Models\Analysis;
use App\Models\Log;
use Illuminate\Support\Facades\DB;

$createLog = fn(array $attributes = []) => Log::create([
  'service' => 'payments',
  'level' => 'ERROR',
  'message' => 'Payment gateway timed out',
  'timestamp' => '2026-11-10T12:00:00Z',
  ...$attributes,
]);

beforeEach(function () {
  config([
    'services.smartlog.api_key' => 'test-secret',
    'services.smartlog.llm_driver' => 'mock',
  ]);

  $this->withHeader('Authorization', 'Bearer test-secret');
});

it('crea un análisis con el mock y guarda los logs seleccionados', function () use ($createLog) {
  $first = $createLog();
  $second = $createLog();
  $createLog(['message' => 'Log no seleccionado']);

  $selectedIds = [$first->getKey(), $second->getKey()];

  $response = $this->postJson('/api/v1/logs/analyze', [
    'log_ids' => $selectedIds,
  ])->assertCreated()
    ->assertJsonStructure(['id']);

  $analysisId = $response->json('id');
  $analysis = Analysis::findOrFail($analysisId);
  $result = $analysis->result;

  expect($analysisId)->toBeInt()
    ->and($analysis->status)->toBe(AnalysisStatusEnum::Completed)
    ->and($analysis->error)->toBeNull()
    ->and($result['summary'])->toBeString()
    ->and($result['categories'])->toBe(['timeout'])
    ->and($result['suggestions'])->toHaveCount(1)
    ->and($analysis->logs()->orderBy('logs.id')->get()->modelKeys())
    ->toBe($selectedIds);
});

it('rechaza selecciones inválidas sin crear análisis', function (
  array $payload,
  string $errorField,
) {
  $analysisCount = Analysis::count();
  $relationCount = DB::table('analysis_logs')->count();

  $this->postJson('/api/v1/logs/analyze', $payload)
    ->assertUnprocessable()
    ->assertJsonValidationErrors([$errorField]);

  expect(Analysis::count())->toBe($analysisCount)
    ->and(DB::table('analysis_logs')->count())->toBe($relationCount);
})->with([
  'selección ausente' => [[], 'log_ids'],
  'selección vacía' => [['log_ids' => []], 'log_ids'],
  'valor en lugar de array' => [['log_ids' => 7], 'log_ids'],
  'ID cero' => [['log_ids' => [0]], 'log_ids.0'],
  'ID decimal' => [['log_ids' => [7.5]], 'log_ids.0'],
]);

it('rechaza IDs duplicados', function () use ($createLog) {
  $log = $createLog();

  $analysisCount = Analysis::count();
  $relationCount = DB::table('analysis_logs')->count();

  $this->postJson('/api/v1/logs/analyze', [
    'log_ids' => [$log->getKey(), $log->getKey()],
  ])->assertUnprocessable()
    ->assertJsonValidationErrors(['log_ids.0']);

  expect(Analysis::count())->toBe($analysisCount)
    ->and(DB::table('analysis_logs')->count())->toBe($relationCount);
});

it('rechaza IDs inexistentes sin crear un análisis', function () use ($createLog) {
  $existing = $createLog();
  $missing = $createLog();

  $missingId = $missing->getKey();
  $missing->delete();

  $analysisCount = Analysis::count();
  $relationCount = DB::table('analysis_logs')->count();

  $this->postJson('/api/v1/logs/analyze', [
    'log_ids' => [$existing->getKey(), $missingId],
  ])->assertUnprocessable()
    ->assertJsonValidationErrors(['log_ids']);

  expect(Analysis::count())->toBe($analysisCount)
    ->and(DB::table('analysis_logs')->count())->toBe($relationCount);
});

it('registra el análisis como fallido cuando el proveedor lanza una excepción', function () use ($createLog) {
  $log = $createLog();
  $error = 'El proveedor no respondió.';

  $analyzer = Mockery::mock(LogAnalyzer::class);
  $analyzer->shouldReceive('analyze')
    ->once()
    ->andThrow(new RuntimeException($error));

  app()->instance(LogAnalyzer::class, $analyzer);

  $response = $this->postJson('/api/v1/logs/analyze', [
    'log_ids' => [$log->getKey()],
  ])->assertStatus(502)
    ->assertJsonStructure(['id', 'message']);

  $analysis = Analysis::findOrFail($response->json('id'));

  expect($analysis->status)->toBe(AnalysisStatusEnum::Failed)
    ->and($analysis->result)->toBeNull()
    ->and($analysis->error)->toBe($error)
    ->and($analysis->logs()->get()->modelKeys())
    ->toBe([$log->getKey()]);
});

it('registra el análisis como fallido cuando el resultado es inválido', function () use ($createLog) {
  $log = $createLog();

  $analyzer = Mockery::mock(LogAnalyzer::class);
  $analyzer->shouldReceive('analyze')
    ->once()
    ->andReturn(['summary' => 7]);

  app()->instance(LogAnalyzer::class, $analyzer);

  $response = $this->postJson('/api/v1/logs/analyze', [
    'log_ids' => [$log->getKey()],
  ])->assertStatus(502)
    ->assertJsonStructure(['id', 'message']);

  $analysis = Analysis::findOrFail($response->json('id'));

  expect($analysis->status)->toBe(AnalysisStatusEnum::Failed)
    ->and($analysis->result)->toBeNull()
    ->and($analysis->error)->toBeString()
    ->and($analysis->logs()->get()->modelKeys())
    ->toBe([$log->getKey()]);
});

it('exige autenticación para crear un análisis', function () {
  $this->withoutHeader('Authorization')
    ->postJson('/api/v1/logs/analyze', [
      'log_ids' => [],
    ])->assertUnauthorized();
});
