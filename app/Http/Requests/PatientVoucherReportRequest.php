<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PatientVoucherReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'patient' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_from.date' => 'La fecha inicial no es válida.',
            'date_to.date' => 'La fecha final no es válida.',
            'date_to.after_or_equal' => 'La fecha final debe ser posterior o igual a la fecha inicial.',
        ];
    }

    /** @return array{date_from: string, date_to: string} */
    public function dateRange(): array
    {
        $validated = $this->validated();

        return [
            'date_from' => $validated['date_from'] ?? today()->toDateString(),
            'date_to' => $validated['date_to'] ?? today()->toDateString(),
        ];
    }
}
