<?php

namespace App\Contracts;

interface LogAnalyzer
{
  /**
   * @param list<array{
   *   id: int,
   *   service: string,
   *   level: string,
   *   message: string,
   *   timestamp: string
   * }> $logs
   *
   * @param array{
   *   by_service: list<array{service: string, total: int}>,
   *   by_level: list<array{level: string, total: int}>
   * } $counts
   *
   * @return array{
   *  summary: string,
   *  categories: list<string>,
   *  suggestions: list<string>
   * }
   */
  public function analyze(array $logs, array $counts): array;
}
