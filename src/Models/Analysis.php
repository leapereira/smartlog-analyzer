<?php

namespace App\Models;

use App\Enums\AnalysisStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Analysis extends Model
{
  protected $table = 'analyses';

  protected $fillable = [
    'status',
    'result',
    'error',
  ];

  protected function casts(): array
  {
    return [
      'status' => AnalysisStatusEnum::class,
      'result' => 'array',
    ];
  }

  public function logs(): BelongsToMany
  {
    return $this->belongsToMany(
      Log::class,
      'analysis_logs',
      'analysis_id',
      'log_id',
    );
  }
}
