<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PrintAgentHeartbeatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->has('print_agent');
    }

    protected function prepareForValidation(): void
    {
        $printers = collect($this->input('available_printers', []))
            ->filter(fn ($printer) => is_string($printer))
            ->map(fn (string $printer) => trim($printer))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->merge(['available_printers' => $printers]);
    }

    public function rules(): array
    {
        return [
            'version' => ['required', 'string', 'max:32'],
            'ticket_printer' => ['nullable', 'string', 'max:255'],
            'quote_printer' => ['nullable', 'string', 'max:255'],
            'available_printers' => ['required', 'array', 'max:100'],
            'available_printers.*' => ['string', 'max:255'],
        ];
    }
}
