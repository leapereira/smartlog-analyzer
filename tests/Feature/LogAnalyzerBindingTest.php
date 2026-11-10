<?php

use App\Analyzers\MockLogAnalyzer;
use App\Contracts\LogAnalyzer;

it('resuelve el analizador mock cuando está configurado', function () {
  config(['services.smartlog.llm_driver' => 'mock']);

  expect(app(LogAnalyzer::class))
    ->toBeInstanceOf(MockLogAnalyzer::class);
});

it('rechaza proveedores de análisis sin implementación', function (string $driver) {
  config(['services.smartlog.llm_driver' => $driver]);

  expect(fn() => app(LogAnalyzer::class))
    ->toThrow(InvalidArgumentException::class);
})->with(['openai', 'desconocido']);
