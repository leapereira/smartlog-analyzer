<?php

namespace App\Enums;

enum AnalysisStatusEnum: string
{
  case Processing = 'processing';
  case Completed = 'completed';
  case Failed = 'failed';
}
