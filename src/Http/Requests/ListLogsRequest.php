<?php

namespace App\Http\Requests;

use DateTimeImmutable;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

#[QueryParameter('service', description: 'Filtra por nombre del servicio.', example: 'payments')]
#[QueryParameter('level', description: 'Filtra por nivel; se convierte a mayúsculas. Ejemplos: INFO, WARN, ERROR.', example: 'ERROR')]
#[QueryParameter('from', description: 'Inicio del rango, con segundos y zona horaria.', format: 'date-time', example: '2026-11-11T12:00:00Z')]
#[QueryParameter('to', description: 'Fin del rango, igual o posterior a from.', format: 'date-time', example: '2026-11-11T13:00:00Z')]
#[QueryParameter('page', description: 'Número de página, desde 1.', default: 1, example: 1)]
#[QueryParameter('per_page', description: 'Logs por página, entre 1 y 100.', default: 20, example: 10)]
class ListLogsRequest extends FormRequest
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
    $dateRules = [
      'sometimes',
      'required',
      'date_format:Y-m-d\TH:i:sP,Y-m-d\TH:i:s\Z',
    ];

    return [
      'service' => ['sometimes', 'required', 'string'],
      'level' => ['sometimes', 'required', 'string'],
      'from' => $dateRules,
      'to' => $dateRules,
      'page' => ['sometimes', 'required', 'integer', 'min:1'],
      'per_page' => ['sometimes', 'required', 'integer', 'min:1', 'max:100'],
    ];
  }

  public function after(): array
  {
    return [
      function (Validator $validator): void {
        if (
          ! $this->has(['from', 'to']) ||
          $validator->errors()->hasAny(['from', 'to'])
        ) {
          return;
        }

        $from = new DateTimeImmutable($this->input('from'));
        $to = new DateTimeImmutable($this->input('to'));

        if ($to < $from) {
          $validator->errors()->add(
            'to',
            'The to field must be a date after or equal to from.',
          );
        }
      },
    ];
  }
}
