<?php

namespace App\Http\Requests;

use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Foundation\Http\FormRequest;

#[BodyParameter('service', description: 'Servicio que produjo el log.', example: 'payments')]
#[BodyParameter('level', description: 'Nivel del log; se convierte a mayúsculas. Ejemplos: INFO, WARN, ERROR.', example: 'ERROR')]
#[BodyParameter('message', description: 'Mensaje del evento.', example: 'Se agotó el tiempo de espera de pagos.')]
#[BodyParameter('timestamp', description: 'Fecha del evento con segundos y zona horaria.', format: 'date-time', example: '2026-11-11T12:00:00Z')]
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
