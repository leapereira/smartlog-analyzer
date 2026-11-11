<?php

use App\Analyzers\OpenAILogAnalyzer;
use App\Exceptions\AnalyzerTimeoutException;
use GuzzleHttp\Exception\ConnectTimeoutException;
use GuzzleHttp\Promise\Create;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
  config([
    'services.openai.api_key' => 'test-openai-key',
    'services.openai.model' => 'gpt-4.1-mini',
    'services.openai.timeout' => 30,
    'services.openai.connect_timeout' => 5,
    'services.openai.max_output_tokens' => 1500,
  ]);

  Http::preventStrayRequests();
});

it('envía los logs y devuelve el resultado del análisis', function () {
  $logs = [[
    'id' => 7,
    'service' => 'payments',
    'level' => 'ERROR',
    'message' => 'Payment gateway timed out',
    'timestamp' => '2026-11-10T12:00:00.000000Z',
  ]];
  $counts = [
    'by_service' => [['service' => 'payments', 'total' => 1]],
    'by_level' => [['level' => 'ERROR', 'total' => 1]],
  ];
  $expected = [
    'summary' => 'Se detectó un tiempo de espera.',
    'categories' => ['timeout'],
    'suggestions' => ['Revisar la latencia.'],
  ];

  Http::fake([
    'https://api.openai.com/v1/responses' => Http::response([
      'status' => 'completed',
      'output' => [[
        'type' => 'message',
        'content' => [['type' => 'output_text', 'text' => json_encode($expected, JSON_THROW_ON_ERROR)]],
      ]],
    ]),
  ]);

  $result = app(OpenAILogAnalyzer::class)->analyze($logs, $counts);
  $request = Http::recorded()->first()[0];
  $payload = $request->data();

  expect($result)->toBe($expected)
    ->and($request->url())->toBe('https://api.openai.com/v1/responses')
    ->and($request->hasHeader('Authorization', 'Bearer test-openai-key'))->toBeTrue()
    ->and($payload['model'])->toBe('gpt-4.1-mini')
    ->and(json_decode($payload['input'], true, 512, JSON_THROW_ON_ERROR))
    ->toBe(['logs' => $logs, 'counts' => $counts])
    ->and($payload['max_output_tokens'])->toBe(1500)
    ->and($payload['store'])->toBeFalse()
    ->and($payload['text']['format']['type'])->toBe('json_schema')
    ->and($payload['text']['format']['strict'])->toBeTrue()
    ->and($payload['text']['format']['schema']['required'])
    ->toBe(['summary', 'categories', 'suggestions'])
    ->and($payload['text']['format']['schema']['additionalProperties'])->toBeFalse();

  Http::assertSentCount(1);
});

it('encuentra el resultado aunque haya otros elementos antes del mensaje', function () {
  $expected = ['summary' => 'Sin problemas.', 'categories' => [], 'suggestions' => []];

  Http::fake([
    'https://api.openai.com/v1/responses' => Http::response([
      'status' => 'completed',
      'output' => [
        ['type' => 'reasoning', 'summary' => []],
        ['type' => 'message', 'content' => [
          ['type' => 'other'],
          ['type' => 'output_text', 'text' => json_encode($expected, JSON_THROW_ON_ERROR)],
        ]],
      ],
    ]),
  ]);

  expect(app(OpenAILogAnalyzer::class)->analyze([], []))->toBe($expected);
});

it('rechaza una respuesta incompleta', function () {
  Http::fake([
    'https://api.openai.com/v1/responses' => Http::response([
      'status' => 'incomplete',
      'output' => [],
    ]),
  ]);

  expect(fn() => app(OpenAILogAnalyzer::class)->analyze([], []))
    ->toThrow(UnexpectedValueException::class, 'OpenAI no completó el análisis.');
});

it('rechaza una respuesta que contiene una negativa del modelo', function () {
  Http::fake([
    'https://api.openai.com/v1/responses' => Http::response([
      'status' => 'completed',
      'output' => [[
        'type' => 'message',
        'content' => [['type' => 'refusal', 'refusal' => 'No puedo analizar estos datos.']],
      ]],
    ]),
  ]);

  expect(fn() => app(OpenAILogAnalyzer::class)->analyze([], []))
    ->toThrow(UnexpectedValueException::class, 'OpenAI rechazó el análisis.');
});

it('rechaza un resultado que no es JSON válido', function () {
  Http::fake([
    'https://api.openai.com/v1/responses' => Http::response([
      'status' => 'completed',
      'output' => [[
        'type' => 'message',
        'content' => [['type' => 'output_text', 'text' => '{incompleto']],
      ]],
    ]),
  ]);

  expect(fn() => app(OpenAILogAnalyzer::class)->analyze([], []))
    ->toThrow(UnexpectedValueException::class, 'OpenAI devolvió un resultado inválido.');
});

it('rechaza una respuesta sin texto generado', function () {
  Http::fake([
    'https://api.openai.com/v1/responses' => Http::response([
      'status' => 'completed',
      'output' => [['type' => 'message', 'content' => []]],
    ]),
  ]);

  expect(fn() => app(OpenAILogAnalyzer::class)->analyze([], []))
    ->toThrow(UnexpectedValueException::class, 'OpenAI no devolvió un resultado.');
});

it('lanza una excepción si OpenAI responde con un error HTTP', function () {
  Http::fake([
    'https://api.openai.com/v1/responses' => Http::response(['error' => ['message' => 'Error interno']], 500),
  ]);

  expect(fn() => app(OpenAILogAnalyzer::class)->analyze([], []))
    ->toThrow(RequestException::class);
});

it('identifica un timeout del cliente HTTP', function () {
  Http::fake([
    'https://api.openai.com/v1/responses' => fn($request) => Create::rejectionFor(
      new ConnectTimeoutException('Se agotó el tiempo de conexión.', $request->toPsrRequest()),
    ),
  ]);

  expect(fn() => app(OpenAILogAnalyzer::class)->analyze([], []))
    ->toThrow(AnalyzerTimeoutException::class, 'El analizador superó el tiempo de espera.');
});

it('identifica un timeout indicado por cURL', function () {
  Http::fake([
    'https://api.openai.com/v1/responses' => Http::failedConnection('cURL error 28: Operation timed out'),
  ]);

  expect(fn() => app(OpenAILogAnalyzer::class)->analyze([], []))
    ->toThrow(AnalyzerTimeoutException::class);
});

it('mantiene los demás fallos de conexión como errores de conexión', function () {
  Http::fake([
    'https://api.openai.com/v1/responses' => Http::failedConnection(),
  ]);

  expect(fn() => app(OpenAILogAnalyzer::class)->analyze([], []))
    ->toThrow(ConnectionException::class);
});

it('exige una clave de OpenAI antes de enviar la solicitud', function () {
  config(['services.openai.api_key' => '']);

  expect(fn() => app(OpenAILogAnalyzer::class)->analyze([], []))
    ->toThrow(InvalidArgumentException::class, 'La clave de OpenAI no está configurada.');

  Http::assertNothingSent();
});
