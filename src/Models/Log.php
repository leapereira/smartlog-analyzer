<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Log extends Model
{
  public const UPDATED_AT = null;

  protected $fillable = [
    'service',
    'level',
    'message',
    'timestamp',
  ];

  protected $dateFormat = 'Y-m-d H:i:s.uP';

  protected function casts(): array
  {
    return [
      'timestamp' => 'datetime',
    ];
  }

  public function analyses(): BelongsToMany
  {
    return $this->belongsToMany(
      Analysis::class,
      'analysis_logs',
      'log_id',
      'analysis_id',
    );
  }
}
