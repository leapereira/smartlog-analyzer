<?php

namespace App\Exceptions;

use RuntimeException;

class AnalysisInProgressException extends RuntimeException
{
  public function __construct()
  {
    parent::__construct('Ya hay un análisis en curso para estos logs. Reintentá en unos segundos.');
  }
}
