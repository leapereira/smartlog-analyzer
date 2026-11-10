<?php

namespace App\Queries;

use App\Models\Log;

class ListLogsQuery
{
  public function execute(array $filters): array
  {
    $query = Log::query();

    if (isset($filters['service'])) {
      $query->where('service', $filters['service']);
    }

    if (isset($filters['level'])) {
      $query->where('level', $filters['level']);
    }

    if (isset($filters['from'])) {
      $query->where('timestamp', '>=', $filters['from']);
    }

    if (isset($filters['to'])) {
      $query->where('timestamp', '<=', $filters['to']);
    }

    $byService = (clone $query)
      ->select('service')
      ->selectRaw('COUNT(*) AS total')
      ->groupBy('service')
      ->orderBy('service')
      ->get()
      ->map(fn (Log $log) => [
        'service' => $log->service,
        'total' => (int) $log->total,
      ])
      ->all();

    $byLevel = (clone $query)
      ->select('level')
      ->selectRaw('COUNT(*) AS total')
      ->groupBy('level')
      ->orderBy('level')
      ->get()
      ->map(fn (Log $log) => [
        'level' => $log->level,
        'total' => (int) $log->total,
      ])
      ->all();

    $paginator = (clone $query)
      ->orderByDesc('timestamp')
      ->orderByDesc('id')
      ->paginate(
        (int) ($filters['per_page'] ?? 20),
        ['*'],
        'page',
        (int) ($filters['page'] ?? 1),
      );

    return [
      'paginator' => $paginator,
      'by_service' => $byService,
      'by_level' => $byLevel,
    ];
  }
}
