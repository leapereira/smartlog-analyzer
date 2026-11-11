<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnalyzeLogsRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'log_ids' => ['required', 'array', 'list'],
      'log_ids.*' => ['required', 'integer:strict', 'min:1', 'distinct'],
    ];
  }
}
