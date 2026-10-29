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
            'patient_name' => ['nullable', 'string', 'max:255'],
            'room_number' => ['nullable', 'string', 'max:100'],
            'patient' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_from.date' => 'La fecha inicial no es válida.',
            'date_to.date' => 'La fecha final no es válida.',
            'date_to.after_or_equal' => 'La fecha final debe ser posterior o igual a la fecha inicial.',
            'patient_name.max' => 'El nombre del paciente no puede exceder 255 caracteres.',
            'room_number.max' => 'El número de habitación no puede exceder 100 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'patient_name' => $this->trimmedOrNull('patient_name'),
            'room_number' => $this->trimmedOrNull('room_number'),
        ]);
    }

    /** @return array{date_from: string, date_to: string, patient_name: ?string, room_number: ?string} */
    public function reportFilters(): array
    {
        $validated = $this->validated();

        return [
            'date_from' => $validated['date_from'] ?? today()->toDateString(),
            'date_to' => $validated['date_to'] ?? today()->toDateString(),
            'patient_name' => $validated['patient_name'] ?? null,
            'room_number' => $validated['room_number'] ?? null,
        ];
    }

    private function trimmedOrNull(string $key): ?string
    {
        $value = trim((string) $this->input($key, ''));

        return $value === '' ? null : $value;
    }
}
