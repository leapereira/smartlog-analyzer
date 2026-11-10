<?php

namespace App\Queries;

use App\Models\Log;
use Illuminate\Database\Eloquent\Builder;

class LogCountsQuery
{
  /**
   * @param Builder<Log> $query
   */
  public function execute(Builder $query): array
  {
    return [
      'by_service' => $this->countBy($query, 'service'),
      'by_level' => $this->countBy($query, 'level'),
    ];
  }

  private function countBy(Builder $query, string $column): array
  {
    return (clone $query)
      ->reorder()
      ->select($column)
      ->selectRaw('COUNT(*) AS total')
      ->groupBy($column)
      ->orderBy($column)
      ->get()
      ->map(fn (Log $log) => [
        $column => $log->getAttribute($column),
        'total' => (int) $log->getAttribute('total'),
      ])
      ->all();
  }
}
