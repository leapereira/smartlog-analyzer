<?php

use App\Analyzers\MockLogAnalyzer;
use App\Analyzers\OpenAILogAnalyzer;
use App\Contracts\LogAnalyzer;

it('resuelve el analizador mock cuando está configurado', function () {
  config(['services.smartlog.llm_driver' => 'mock']);

  expect(app(LogAnalyzer::class))
    ->toBeInstanceOf(MockLogAnalyzer::class);
});

it('resuelve el analizador de OpenAI aunque la clave todavía esté vacía', function () {
  config([
    'services.smartlog.llm_driver' => 'openai',
    'services.openai.api_key' => '',
  ]);

  expect(app(LogAnalyzer::class))
    ->toBeInstanceOf(OpenAILogAnalyzer::class);
});

it('rechaza proveedores de análisis sin implementación', function () {
  config(['services.smartlog.llm_driver' => 'desconocido']);

  expect(fn() => app(LogAnalyzer::class))
    ->toThrow(InvalidArgumentException::class);
});
