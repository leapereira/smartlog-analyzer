<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
  config(['services.smartlog.api_key' => 'test-secret']);
});

it('responde 200 cuando PostgreSQL y Redis están disponibles', function () {
  DB::shouldReceive('select')
    ->once()
    ->with('SELECT 1')
    ->andReturn([(object) ['?column?' => 1]]);

  Redis::shouldReceive('ping')
    ->once()
    ->andReturn(true);

  $this->withHeader('Authorization', 'Bearer test-secret')
    ->getJson('/api/v1/health')
    ->assertOk()
    ->assertExactJson(['status' => 'ok']);
});

it('responde 503 cuando PostgreSQL no está disponible', function () {
  DB::shouldReceive('select')
    ->once()
    ->with('SELECT 1')
    ->andThrow(new RuntimeException('PostgreSQL no disponible'));

  Redis::shouldReceive('ping')
    ->andReturn(true);

  $this->withHeader('Authorization', 'Bearer test-secret')
    ->getJson('/api/v1/health')
    ->assertStatus(503)
    ->assertExactJson(['status' => 'unavailable']);
});

it('responde 503 cuando Redis no está disponible', function () {
  DB::shouldReceive('select')
    ->once()
    ->with('SELECT 1')
    ->andReturn([(object) ['?column?' => 1]]);

  Redis::shouldReceive('ping')
    ->once()
    ->andThrow(new RuntimeException('Redis no disponible'));

  $this->withHeader('Authorization', 'Bearer test-secret')
    ->getJson('/api/v1/health')
    ->assertStatus(503)
    ->assertExactJson(['status' => 'unavailable']);
});