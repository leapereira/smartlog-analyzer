<?php

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
  Gate::define('viewApiDocs', fn (?Authenticatable $user = null): bool => true);

  config(['scramble.cache.store' => null]);

  Http::preventStrayRequests();
});

it('muestra el visor de la documentación en la API v1', function () {
  $this->get('/api/v1/docs')
    ->assertOk()
    ->assertSee('SmartLog Analyzer API');
});

it('publica las cinco operaciones de la API v1', function () {
  $document = $this->getJson('/api/v1/docs.json')->assertOk()->json();
  $operations = array_map(fn (array $path) => array_keys($path), $document['paths']);

  expect($operations)->toEqualCanonicalizing([
    '/health' => ['get'],
    '/logs' => ['get', 'post'],
    '/logs/analyze' => ['post'],
    '/analysis/{id}' => ['get'],
  ])->and(parse_url($document['servers'][0]['url'], PHP_URL_PATH))->toBe('/api/v1');
});

it('declara la autenticación Bearer para toda la API', function () {
  $document = $this->getJson('/api/v1/docs.json')->assertOk()->json();

  expect($document['components']['securitySchemes']['http'])->toBe([
    'type' => 'http',
    'scheme' => 'bearer',
  ])->and($document['security'])->toBe([['http' => []]]);
});

it('documenta cache como parámetro opcional de la URL y log_ids en el cuerpo', function () {
  $document = $this->getJson('/api/v1/docs.json')->assertOk()->json();
  $operation = $document['paths']['/logs/analyze']['post'];
  $cache = collect($operation['parameters'])->firstWhere('name', 'cache');
  $body = $document['components']['schemas']['AnalyzeLogsRequest'];

  expect($cache['in'])->toBe('query')
    ->and($cache['required'] ?? false)->toBeFalse()
    ->and($cache['schema']['default'])->toBe('true')
    ->and($cache['schema']['enum'])->toBe(['true', 'false'])
    ->and($body['properties'])->toHaveKey('log_ids')->not->toHaveKey('cache')
    ->and($body['required'])->toContain('log_ids');
});

it('documenta los códigos de respuesta de la operación', function (
  string $path,
  string $method,
  array $expectedStatuses,
) {
  $document = $this->getJson('/api/v1/docs.json')->assertOk()->json();
  $responses = $document['paths'][$path][$method]['responses'];

  expect(array_keys($responses))->toEqualCanonicalizing($expectedStatuses);
})->with([
  'consultar logs' => ['/logs', 'get', [200, 401, 422, 429, 503]],
  'registrar un log' => ['/logs', 'post', [201, 401, 422, 429, 503]],
  'analizar logs' => ['/logs/analyze', 'post', [200, 201, 401, 409, 422, 429, 502, 503, 504]],
  'consultar un análisis' => ['/analysis/{id}', 'get', [200, 401, 404, 429, 503]],
  'consultar el estado' => ['/health', 'get', [200, 401, 503]],
]);
