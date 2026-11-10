<?php

namespace App\Analyzers;

use App\Contracts\LogAnalyzer;
use Illuminate\Support\Str;

class MockLogAnalyzer implements LogAnalyzer
{
  private const PATTERNS = [
    'timeout' => [
      'phrases' => ['timed out', 'request timeout', 'connection timeout', 'exceeded deadline'],
      'label' => 'Timeouts',
      'suggestion' => 'Revisar la latencia y los tiempos de espera de las dependencias.',
    ],
    'rate_limit' => [
      'phrases' => ['rate limit reached', 'status=429', 'too many requests'],
      'label' => 'Límites de solicitudes alcanzados',
      'suggestion' => 'Respetar el tiempo de reintento y reducir la frecuencia de solicitudes al proveedor.',
    ],
    'connection_failure' => [
      'phrases' => ['connection failed', 'connection refused', 'connection reset'],
      'label' => 'Fallos de conexión',
      'suggestion' => 'Verificar la disponibilidad de la dependencia y las conexiones de su pool.',
    ],
    'dependency_unavailable' => [
      'phrases' => ['status=503', 'service unavailable'],
      'label' => 'Dependencias no disponibles',
      'suggestion' => 'Comprobar el estado de la dependencia antes de reintentar.',
    ],
  ];

  // Analiza los logs y devuelve un resumen, categorías y sugerencias
  public function analyze(array $logs, array $counts): array
  {
    $summary = sprintf(
      'Se analizaron %d logs. Los servicios fueron %s. Los niveles fueron %s.',
      count($logs),
      $this->formatCounts(array_column($counts['by_service'], 'total', 'service')),
      $this->formatCounts(array_column($counts['by_level'], 'total', 'level')),
    );
    $categories = [];
    $suggestions = [];

    foreach (self::PATTERNS as $category => $pattern) {
      $matchesByService = $this->countMatchesByService($logs, $pattern['phrases']);

      if ($matchesByService === []) {
        continue;
      }

      $summary .= sprintf(' %s en %s.', $pattern['label'], $this->formatCounts($matchesByService));
      $categories[] = $category;
      $suggestions[] = $pattern['suggestion'];
    }

    return [
      'summary' => $summary,
      'categories' => $categories,
      'suggestions' => $suggestions,
    ];
  }

  // Cuenta cuántos logs coinciden con las frases dadas, agrupados por servicio
  // Ejemplo: si $logs contiene 3 logs del servicio "payments" que contienen la frase "timed out",
  // y 2 logs del servicio "orders" que contienen la frase "timed out",
  // el resultado será ['payments' => 3, 'orders' => 2]
  private function countMatchesByService(array $logs, array $phrases): array
  {
    $counts = [];

    foreach ($logs as $log) {
      if (!Str::contains($log['message'], $phrases, ignoreCase: true)) {
        continue;
      }

      $service = $log['service'];
      $counts[$service] = ($counts[$service] ?? 0) + 1;
    }

    return $counts;
  }

  // Formatea un array asociativo de conteos en una cadena legible
  // por ejemplo: ['service1' => 3, 'service2' => 5] se convierte en "service1 (3), service2 (5)"
  private function formatCounts(array $counts): string
  {
    $parts = [];

    foreach ($counts as $name => $total) {
      $parts[] = "{$name} ({$total})";
    }

    return implode(', ', $parts);
  }
}
