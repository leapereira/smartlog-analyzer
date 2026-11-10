<?php

beforeEach(function () {
  config(['services.smartlog.api_key' => 'test-secret']);
});

test('el endpoint de salud responde correctamente', function () {
  $this->withHeader('Authorization', 'Bearer test-secret')
    ->getJson('/api/v1/health')
    ->assertOk()
    ->assertExactJson([
      'status' => 'ok',
    ]);
});
