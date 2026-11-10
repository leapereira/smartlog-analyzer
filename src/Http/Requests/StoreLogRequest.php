<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLogRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  protected function prepareForValidation(): void
  {
    $level = $this->input('level');

    if (is_string($level)) {
      $this->merge(['level' => strtoupper($level)]);
    }
  }

  public function rules(): array
  {
    return [
      'service' => ['required', 'string'],
      'level' => ['required', 'string'],
      'message' => ['required', 'string'],
      'timestamp' => ['required', 'date_format:Y-m-d\TH:i:s\Z,Y-m-d\TH:i:sP'],
    ];
  }
}
