<?php

use Illuminate\Support\Str;

beforeEach(function () {
  config(['services.smartlog.api_key' => 'test-secret']);

  $this->withHeader('Authorization', 'Bearer test-secret');
});

it('guarda un log válido y normaliza su nivel', function (string $timestamp) {
  $response = $this->postJson('/api/v1/logs', [
    'service' => 'payments',
    'level' => 'error',
    'message' => 'Payment gateway timed out',
    'timestamp' => $timestamp,
  ]);

  $response->assertCreated()
    ->assertJsonPath('data.service', 'payments')
    ->assertJsonPath('data.level', 'ERROR')
    ->assertJsonStructure([
      'data' => ['id', 'message', 'timestamp', 'created_at'],
    ]);

  $this->assertDatabaseHas('logs', [
    'id' => $response->json('data.id'),
    'service' => 'payments',
    'level' => 'ERROR',
    'message' => 'Payment gateway timed out',
    'timestamp' => '2026-11-10 12:30:00+00:00',
  ]);
})->with([
  'fecha en UTC' => ['2026-11-10T12:30:00Z'],
  'fecha con offset' => ['2026-11-10T09:30:00-03:00'],
]);

it('rechaza un log sin mensaje', function () {
  $service = 'test-' . Str::uuid();

  $this->postJson('/api/v1/logs', [
    'service' => $service,
    'level' => 'ERROR',
    'timestamp' => '2026-11-10T12:30:00Z',
  ])->assertUnprocessable()
    ->assertJsonValidationErrors(['message']);

  $this->assertDatabaseMissing('logs', ['service' => $service]);
});

it('rechaza un timestamp sin zona horaria', function () {
  $this->postJson('/api/v1/logs', [
    'service' => 'payments',
    'level' => 'ERROR',
    'message' => 'Payment gateway timed out',
    'timestamp' => '2026-11-10T09:30:00',
  ])->assertUnprocessable()
    ->assertJsonValidationErrors(['timestamp']);
});
