<?php

namespace App\Queries;

use App\Models\Log;

class ListLogsQuery
{
  public function __construct(private LogCountsQuery $logCountsQuery)
  {
  }

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

    $counts = $this->logCountsQuery->execute($query);

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
      'by_service' => $counts['by_service'],
      'by_level' => $counts['by_level'],
    ];
  }
}
