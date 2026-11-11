<?php

namespace App\Clients;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use JsonException;
use UnexpectedValueException;

class OpenAIClient
{
  private const URL = 'https://api.openai.com/v1/responses';

  public function createStructuredResponse(
    string $instructions,
    array $input,
    string $schemaName,
    array $schema,
  ): array {
    $apiKey = config('services.openai.api_key');
    $model = config('services.openai.model');

    if (!is_string($apiKey) || trim($apiKey) === '') {
      throw new InvalidArgumentException('La clave de OpenAI no está configurada.');
    }

    if (!is_string($model) || trim($model) === '') {
      throw new InvalidArgumentException('El modelo de OpenAI no está configurado.');
    }

    $response = Http::withToken($apiKey)
      ->acceptJson()
      ->connectTimeout(config('services.openai.connect_timeout'))
      ->timeout(config('services.openai.timeout'))
      ->post(self::URL, [
        'model' => $model,
        'instructions' => $instructions,
        'input' => json_encode($input, JSON_THROW_ON_ERROR),
        'max_output_tokens' => config('services.openai.max_output_tokens'),
        'store' => false,
        'text' => [
          'format' => [
            'type' => 'json_schema',
            'name' => $schemaName,
            'strict' => true,
            'schema' => $schema,
          ],
        ],
      ])
      ->throw();

    $body = $response->json();

    if (!is_array($body) || ($body['status'] ?? null) !== 'completed') {
      throw new UnexpectedValueException('OpenAI no completó el análisis.');
    }

    if (!is_array($body['output'] ?? null)) {
      throw new UnexpectedValueException('OpenAI devolvió una respuesta inválida.');
    }

    return $this->extractResult($body['output']);
  }

  private function extractResult(array $output): array
  {
    $text = null;

    foreach ($output as $item) {
      if (!is_array($item) || ($item['type'] ?? null) !== 'message' || !is_array($item['content'] ?? null)) {
        continue;
      }

      foreach ($item['content'] as $content) {
        if (!is_array($content)) {
          continue;
        }

        if (($content['type'] ?? null) === 'refusal') {
          throw new UnexpectedValueException('OpenAI rechazó el análisis.');
        }

        if (($content['type'] ?? null) === 'output_text' && is_string($content['text'] ?? null)) {
          $text ??= $content['text'];
        }
      }
    }

    if ($text === null) {
      throw new UnexpectedValueException('OpenAI no devolvió un resultado.');
    }

    try {
      $result = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
      throw new UnexpectedValueException('OpenAI devolvió un resultado inválido.', 0, $exception);
    }

    if (!is_array($result)) {
      throw new UnexpectedValueException('OpenAI devolvió un resultado inválido.');
    }

    return $result;
  }
}
