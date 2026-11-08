<?php

test('health endpoint returns OK', function () {
  $this->getJson('/api/v1/health')
    ->assertOk()
    ->assertExactJson([
      'status' => 'ok',
    ]);
});
