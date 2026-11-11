<?php

use App\Cache\AnalysisCache;

beforeEach(function () {
  config([
    'services.smartlog.llm_driver' => 'mock',
    'services.openai.model' => 'gpt-4.1-mini',
    'services.openai.max_output_tokens' => 1500,
  ]);
});

it('usa la misma clave para los mismos IDs en distinto orden', function () {
  $cache = app(AnalysisCache::class);

  expect($cache->keyFor([12, 5]))->toBe($cache->keyFor([5, 12]));
});

it('usa claves diferentes para selecciones o proveedores diferentes', function () {
  $cache = app(AnalysisCache::class);
  $mockKey = $cache->keyFor([5, 12]);

  expect($mockKey)->not->toBe($cache->keyFor([5, 13]));

  config(['services.smartlog.llm_driver' => 'openai']);

  expect($cache->keyFor([5, 12]))->not->toBe($mockKey);
});

it('cambia la clave de OpenAI si cambia el modelo o el límite de tokens', function () {
  config(['services.smartlog.llm_driver' => 'openai']);
  $cache = app(AnalysisCache::class);
  $original = $cache->keyFor([5, 12]);

  config(['services.openai.model' => 'otro-modelo']);
  expect($cache->keyFor([5, 12]))->not->toBe($original);

  config([
    'services.openai.model' => 'gpt-4.1-mini',
    'services.openai.max_output_tokens' => 500,
  ]);
  expect($cache->keyFor([5, 12]))->not->toBe($original);
});
