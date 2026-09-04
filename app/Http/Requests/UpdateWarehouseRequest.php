<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('warehouses', 'name')->ignore($this->route('warehouse')),
            ],
            'default_nursing_cabinet_id' => [
                'nullable',
                'integer',
                Rule::exists('cabinets', 'id')->where(
                    fn ($query) => $query->where('warehouse_id', $this->route('warehouse')->getKey()),
                ),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'name.string' => 'El nombre debe ser texto.',
            'name.max' => 'El nombre no puede tener más de 255 caracteres.',
            'name.unique' => 'Ya existe un almacén con este nombre.',
            'default_nursing_cabinet_id.exists' => 'El gabinete predeterminado debe pertenecer al almacén seleccionado.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'default_nursing_cabinet_id' => 'gabinete predeterminado de Enfermería',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'default_nursing_cabinet_id' => filled($this->input('default_nursing_cabinet_id'))
                ? $this->integer('default_nursing_cabinet_id')
                : null,
        ]);
    }
}
