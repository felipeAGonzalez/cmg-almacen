<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOperationalSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'warehouse_service_start_time' => ['required', 'date_format:H:i', 'different:warehouse_service_end_time'],
            'warehouse_service_end_time' => ['required', 'date_format:H:i'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'warehouse_service_start_time.required' => 'La hora de inicio es obligatoria.',
            'warehouse_service_start_time.date_format' => 'La hora de inicio debe tener un formato válido.',
            'warehouse_service_start_time.different' => 'La hora de inicio y la hora de fin deben ser diferentes.',
            'warehouse_service_end_time.required' => 'La hora de fin es obligatoria.',
            'warehouse_service_end_time.date_format' => 'La hora de fin debe tener un formato válido.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'warehouse_service_start_time' => 'inicio de atención en almacén',
            'warehouse_service_end_time' => 'fin de atención en almacén',
        ];
    }
}
