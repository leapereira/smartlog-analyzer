<?php

namespace App\Http\Requests;

use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Foundation\Http\FormRequest;

#[BodyParameter(
  'log_ids',
  description: 'Lista no vacía de IDs de logs existentes, sin duplicados.',
  example: [1, 2, 3],
)]
#[QueryParameter(
  'cache',
  description: 'Permite reutilizar un análisis completado. false solicita una ejecución nueva.',
  required: false,
  default: 'true',
  example: 'false',
)]
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

      /** @query */
      'cache' => ['required', 'string', 'in:true,false'],
    ];
  }

  public function useCache(): bool
  {
    return $this->validated('cache') === 'true';
  }
}
