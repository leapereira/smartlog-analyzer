<?php

namespace App\Exceptions;

use App\Models\Analysis;
use RuntimeException;
use Throwable;

class AnalysisFailedException extends RuntimeException
{
  public function __construct(public readonly Analysis $analysis, string $message, Throwable $cause)
  {
    parent::__construct($message, 0, $cause);
  }
}
