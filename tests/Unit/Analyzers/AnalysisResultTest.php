<?php

use App\Analyzers\AnalysisResult;

$validResult = [
  'summary' => 'Los logs reportan errores de conexión.',
  'categories' => ['timeout', 'conexión'],
  'suggestions' => ['Revisar la latencia.', 'Comprobar la conectividad.'],
];

it('conserva el contenido y el orden de un resultado válido', function () use ($validResult) {
  expect(AnalysisResult::fromArray($validResult)->toArray())
    ->toBe($validResult);
});

it('acepta categorías y sugerencias vacías', function () use ($validResult) {
  $validResult['categories'] = [];
  $validResult['suggestions'] = [];

  expect(AnalysisResult::fromArray($validResult)->toArray())
    ->toBe($validResult);
});

it('devuelve únicamente los campos del contrato', function () use ($validResult) {
  $data = [...$validResult, 'extra' => 'Dato del proveedor'];

  expect(AnalysisResult::fromArray($data)->toArray())
    ->toBe($validResult);
});

it('rechaza un resultado con un campo ausente', function (string $field) use ($validResult) {
  unset($validResult[$field]);

  expect(fn() => AnalysisResult::fromArray($validResult))
    ->toThrow(InvalidArgumentException::class);
})->with(['summary', 'categories', 'suggestions']);

it('rechaza campos con tipos o listas inválidos', function (
  string $field,
  mixed $value,
) use ($validResult) {
  $validResult[$field] = $value;

  expect(fn() => AnalysisResult::fromArray($validResult))
    ->toThrow(InvalidArgumentException::class);
})->with([
  'resumen nulo' => ['summary', null],
  'resumen numérico' => ['summary', 7],
  'resumen como array' => ['summary', []],
  'categorías nulas' => ['categories', null],
  'categorías como texto' => ['categories', 'timeout'],
  'categorías con claves asociativas' => ['categories', ['tipo' => 'timeout']],
  'categoría numérica' => ['categories', [7]],
  'sugerencias nulas' => ['suggestions', null],
  'sugerencias como texto' => ['suggestions', 'Revisar la latencia.'],
  'sugerencias con claves asociativas' => ['suggestions', ['acción' => 'Revisar.']],
  'sugerencia booleana' => ['suggestions', [false]],
]);
