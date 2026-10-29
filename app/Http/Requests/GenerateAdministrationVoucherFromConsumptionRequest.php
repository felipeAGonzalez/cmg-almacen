<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateAdministrationVoucherFromConsumptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cabinet_id' => ['required', 'integer', 'exists:cabinets,id'],
            'consumption_date' => ['required', 'date', 'before:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'cabinet_id.required' => 'El gabinete es obligatorio.',
            'cabinet_id.exists' => 'El gabinete seleccionado no existe.',
            'consumption_date.required' => 'La fecha de consumo es obligatoria.',
            'consumption_date.before' => 'Sólo se pueden generar vales de consumos de días anteriores.',
        ];
    }
}
