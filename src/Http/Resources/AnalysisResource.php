<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnalysisResource extends JsonResource
{
  public function toArray(Request $request): array
  {
    return [
      'id' => $this->id,
      'status' => $this->status->value,
      /**
       * @var array{
       *   summary: string,
       *   categories: list<string>,
       *   suggestions: list<string>
       * }|null
       */
      'result' => $this->result,
      'error' => $this->error,
    ];
  }
}
