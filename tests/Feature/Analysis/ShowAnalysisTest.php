<?php

use App\Enums\AnalysisStatusEnum;
use App\Models\Analysis;

beforeEach(function () {
  config(['services.smartlog.api_key' => 'test-secret']);

  $this->withHeader('Authorization', 'Bearer test-secret');
});

it('devuelve el resultado de un análisis completado', function () {
  $result = [
    'summary' => 'Se detectó un timeout.',
    'categories' => ['timeout'],
    'suggestions' => ['Revisar la latencia.'],
  ];

  $analysis = Analysis::create([
    'status' => AnalysisStatusEnum::Completed,
    'result' => $result,
  ]);

  $this->getJson('/api/v1/analysis/' . $analysis->getKey())
    ->assertOk()
    ->assertExactJson(['data' => [
      'id' => $analysis->getKey(),
      'status' => 'completed',
      'result' => $result,
      'error' => null,
    ]]);
});

it('devuelve un análisis en proceso sin resultado ni error', function () {
  $analysis = Analysis::create([
    'status' => AnalysisStatusEnum::Processing,
  ]);

  $this->getJson('/api/v1/analysis/' . $analysis->getKey())
    ->assertOk()
    ->assertExactJson(['data' => [
      'id' => $analysis->getKey(),
      'status' => 'processing',
      'result' => null,
      'error' => null,
    ]]);
});

it('devuelve el error de un análisis fallido', function () {
  $analysis = Analysis::create([
    'status' => AnalysisStatusEnum::Failed,
    'error' => 'El proveedor no respondió.',
  ]);

  $this->getJson('/api/v1/analysis/' . $analysis->getKey())
    ->assertOk()
    ->assertExactJson(['data' => [
      'id' => $analysis->getKey(),
      'status' => 'failed',
      'result' => null,
      'error' => 'El proveedor no respondió.',
    ]]);
});

it('devuelve 404 si el análisis no existe', function () {
  $analysis = Analysis::create([
    'status' => AnalysisStatusEnum::Processing,
  ]);
  $analysis->delete();

  $this->getJson('/api/v1/analysis/' . $analysis->getKey())
    ->assertNotFound();
});

it('exige autenticación para consultar un análisis', function () {
  $analysis = Analysis::create([
    'status' => AnalysisStatusEnum::Processing,
  ]);

  $this->withoutHeader('Authorization')
    ->getJson('/api/v1/analysis/' . $analysis->getKey())
    ->assertUnauthorized();
});
