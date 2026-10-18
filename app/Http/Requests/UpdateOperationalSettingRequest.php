<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'warehouse_rest_day' => ['required', 'integer', 'between:1,7'],
            'administration_voucher_delegate_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn (Builder $query): Builder => $query->where('role', UserRole::WAREHOUSE_MANAGER->value),
                ),
                'required_with:administration_voucher_delegate_day',
            ],
            'administration_voucher_delegate_day' => [
                'nullable',
                'integer',
                'between:1,7',
                'required_with:administration_voucher_delegate_user_id',
            ],
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
            'warehouse_rest_day.required' => 'El día de descanso es obligatorio.',
            'warehouse_rest_day.integer' => 'El día de descanso seleccionado no es válido.',
            'warehouse_rest_day.between' => 'El día de descanso seleccionado no es válido.',
            'administration_voucher_delegate_user_id.exists' => 'El almacenista seleccionado no es válido.',
            'administration_voucher_delegate_user_id.required_with' => 'Selecciona al almacenista que cubrirá el descanso administrativo.',
            'administration_voucher_delegate_day.between' => 'El día de suplencia seleccionado no es válido.',
            'administration_voucher_delegate_day.required_with' => 'Selecciona el día semanal de la suplencia.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'warehouse_service_start_time' => 'inicio de atención en almacén',
            'warehouse_service_end_time' => 'fin de atención en almacén',
            'warehouse_rest_day' => 'día de descanso del almacenista',
            'administration_voucher_delegate_user_id' => 'almacenista suplente',
            'administration_voucher_delegate_day' => 'día de descanso administrativo',
        ];
    }
}
