<?php

namespace App\Actions\Results;

use App\Models\Analysis;

final readonly class AnalysisExecutionResult
{
  public function __construct(
    public Analysis $analysis,
    public bool $fromCache,
  ) {}
}
