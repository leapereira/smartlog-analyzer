<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnalyzeLogsRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  protected function prepareForValidation(): void
  {
    $this->merge([
      'cache' => $this->query->has('cache') ? $this->query('cache') : 'true',
    ]);
  }

  public function rules(): array
  {
    return [
      'log_ids' => ['required', 'array', 'list'],
      'log_ids.*' => ['required', 'integer:strict', 'min:1', 'distinct'],
      'cache' => ['required', 'string', 'in:true,false'],
    ];
  }

  public function useCache(): bool
  {
    return $this->validated('cache') === 'true';
  }
}
