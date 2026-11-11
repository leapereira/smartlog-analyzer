<?php

use App\Enums\AnalysisStatusEnum;
use App\Models\Analysis;
use App\Models\Log;
use App\Providers\AppServiceProvider;
use Illuminate\Cache\RateLimiter as CacheRateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

$createLog = fn() => Log::create([
  'service' => 'payments',
  'level' => 'ERROR',
  'message' => 'Payment gateway timed out',
  'timestamp' => '2026-11-10T12:00:00Z',
]);

beforeEach(function () {
  config([
    'services.smartlog.api_key' => 'test-secret',
    'services.smartlog.llm_driver' => 'openai',
    'services.openai.api_key' => 'test-openai-key',
    'services.openai.model' => 'gpt-4.1-mini',
  ]);

  RateLimiter::swap(new CacheRateLimiter(Cache::store('array')));
  (new AppServiceProvider(app()))->boot();

  Http::preventStrayRequests();
  $this->withHeader('Authorization', 'Bearer test-secret');
});

it('guarda el resultado de OpenAI y los logs seleccionados', function () use ($createLog) {
  $first = $createLog();
  $second = $createLog();
  $createLog();
  $selectedIds = [$first->getKey(), $second->getKey()];
  $result = [
    'summary' => 'Se detectaron tiempos de espera en pagos.',
    'categories' => ['timeout'],
    'suggestions' => ['Revisar la latencia del proveedor.'],
  ];

  Http::fake([
    'https://api.openai.com/v1/responses' => Http::response([
      'status' => 'completed',
      'output' => [[
        'type' => 'message',
        'content' => [['type' => 'output_text', 'text' => json_encode($result, JSON_THROW_ON_ERROR)]],
      ]],
    ]),
  ]);

  $response = $this->postJson('/api/v1/logs/analyze', [
    'log_ids' => $selectedIds,
  ])->assertCreated();

  $analysis = Analysis::findOrFail($response->json('id'));
  Http::assertSentCount(1);
  $input = json_decode(Http::recorded()->first()[0]->data()['input'], true, 512, JSON_THROW_ON_ERROR);

  expect($analysis->status)->toBe(AnalysisStatusEnum::Completed)
    ->and($analysis->result)->toBe($result)
    ->and($analysis->error)->toBeNull()
    ->and($analysis->logs()->orderBy('logs.id')->get()->modelKeys())->toBe($selectedIds)
    ->and(array_column($input['logs'], 'id'))->toBe($selectedIds);
});

it('guarda el fallo y devuelve 502 si OpenAI responde con un error', function () use ($createLog) {
  $log = $createLog();
  Http::fake([
    'https://api.openai.com/v1/responses' => Http::response(['error' => 'Error interno'], 500),
  ]);

  $response = $this->postJson('/api/v1/logs/analyze', [
    'log_ids' => [$log->getKey()],
  ])->assertStatus(502)
    ->assertJsonPath('message', 'No se pudo completar el análisis.');

  $analysis = Analysis::findOrFail($response->json('id'));

  expect($analysis->status)->toBe(AnalysisStatusEnum::Failed)
    ->and($analysis->result)->toBeNull()
    ->and($analysis->error)->toBe('No se pudo completar el análisis.')
    ->and($analysis->logs()->get()->modelKeys())->toBe([$log->getKey()]);

  Http::assertSentCount(1);
});

it('guarda el timeout y devuelve 504 con un ID consultable', function () use ($createLog) {
  $log = $createLog();
  Http::fake([
    'https://api.openai.com/v1/responses' => Http::failedConnection('cURL error 28: Operation timed out'),
  ]);

  $response = $this->postJson('/api/v1/logs/analyze', [
    'log_ids' => [$log->getKey()],
  ])->assertStatus(504)
    ->assertJsonPath('message', 'El analizador superó el tiempo de espera.');

  $analysis = Analysis::findOrFail($response->json('id'));

  expect($analysis->status)->toBe(AnalysisStatusEnum::Failed)
    ->and($analysis->result)->toBeNull()
    ->and($analysis->error)->toBe('El analizador superó el tiempo de espera.')
    ->and($analysis->logs()->get()->modelKeys())->toBe([$log->getKey()]);

  $this->getJson('/api/v1/analysis/' . $analysis->getKey())
    ->assertOk()
    ->assertJsonPath('data.status', 'failed');

  Http::assertSentCount(1);
});

it('no consulta OpenAI si la selección es inválida', function () {
  Http::fake();

  $this->postJson('/api/v1/logs/analyze', [
    'log_ids' => [],
  ])->assertUnprocessable();

  Http::assertNothingSent();
});

it('no consulta OpenAI si falta la API key de SmartLog', function () use ($createLog) {
  $log = $createLog();
  Http::fake();

  $this->withoutHeader('Authorization')
    ->postJson('/api/v1/logs/analyze', [
      'log_ids' => [$log->getKey()],
    ])->assertUnauthorized();

  Http::assertNothingSent();
});

it('no vuelve a consultar OpenAI cuando supera el límite de análisis', function () use ($createLog) {
  config(['services.smartlog.log_analysis_rate_limit_per_minute' => 1]);
  $log = $createLog();
  $result = ['summary' => 'Sin problemas.', 'categories' => [], 'suggestions' => []];
  Http::fake([
    'https://api.openai.com/v1/responses' => Http::response([
      'status' => 'completed',
      'output' => [[
        'type' => 'message',
        'content' => [['type' => 'output_text', 'text' => json_encode($result, JSON_THROW_ON_ERROR)]],
      ]],
    ]),
  ]);
  $payload = ['log_ids' => [$log->getKey()]];

  $this->postJson('/api/v1/logs/analyze', $payload)->assertCreated();
  $this->postJson('/api/v1/logs/analyze', $payload)->assertStatus(429);

  Http::assertSentCount(1);
});
