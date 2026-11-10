<?php

namespace App\Analyzers;

use InvalidArgumentException;

final readonly class AnalysisResult
{
  private function __construct(private array $data) {}

  public static function fromArray(array $data): self
  {
    if (!is_string($data['summary'] ?? null)) {
      throw new InvalidArgumentException('El resumen del análisis debe ser un texto.');
    }

    foreach (['categories', 'suggestions'] as $field) {
      $values = $data[$field] ?? null;

      if (!is_array($values) || !array_is_list($values)) {
        throw new InvalidArgumentException("El campo {$field} debe ser una lista de textos.");
      }

      foreach ($values as $value) {
        if (!is_string($value)) {
          throw new InvalidArgumentException("El campo {$field} debe contener solo textos.");
        }
      }
    }

    return new self([
      'summary' => $data['summary'],
      'categories' => $data['categories'],
      'suggestions' => $data['suggestions'],
    ]);
  }

  public function toArray(): array
  {
    return $this->data;
  }
}
