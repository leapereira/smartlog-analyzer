<?php

beforeEach(function () {
  config(['services.smartlog.api_key' => 'test-secret']);
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