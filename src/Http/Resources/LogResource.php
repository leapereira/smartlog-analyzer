<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LogResource extends JsonResource
{
  public function toArray(Request $request): array
  {
    return [
      'id' => $this->id,
      'service' => $this->service,
      'level' => $this->level,
      'message' => $this->message,
      'timestamp' => $this->timestamp->copy()->utc()->format('Y-m-d\TH:i:s.u\Z'),
      'created_at' => $this->created_at?->copy()->utc()->format('Y-m-d\TH:i:s.u\Z'),
    ];
  }
}
