<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FailPrintJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->has('print_agent');
    }

    public function rules(): array
    {
        return [
            'error' => ['required', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return ['error' => 'detalle del error'];
    }
}
