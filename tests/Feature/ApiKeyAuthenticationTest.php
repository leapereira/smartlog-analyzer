<?php

beforeEach(function () {
  config(['services.smartlog.api_key' => 'test-secret']);
});

it('permite solicitudes con el token Bearer configurado', function () {
  $this->withHeader('Authorization', 'Bearer test-secret')
    ->getJson('/api/v1/health')
    ->assertOk()
    ->assertExactJson(['status' => 'ok']);
});

it('rechaza solicitudes sin un token Bearer', function () {
  $this->getJson('/api/v1/health')
    ->assertUnauthorized()
    ->assertExactJson(['message' => 'Unauthenticated.']);
});

it('rechaza solicitudes con un token Bearer incorrecto', function () {
  $this->withHeader('Authorization', 'Bearer wrong-secret')
    ->getJson('/api/v1/health')
    ->assertUnauthorized()
    ->assertExactJson(['message' => 'Unauthenticated.']);
});
