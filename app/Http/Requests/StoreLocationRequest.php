<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $warehouse = $this->route('warehouse');

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('locations', 'name')
                    ->where(fn (Builder $query): Builder => $query->where('warehouse_id', $warehouse->getKey())),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'name.string' => 'El nombre debe ser texto.',
            'name.max' => 'El nombre no puede tener más de 255 caracteres.',
            'name.unique' => 'Ya existe una ubicación con este nombre en el almacén.',
            'description.string' => 'La descripción debe ser texto.',
            'description.max' => 'La descripción no puede tener más de 1000 caracteres.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'nombre', 'description' => 'descripción'];
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        $description = $this->input('description');

        $this->merge([
            'name' => is_string($name) ? trim($name) : $name,
            'description' => is_string($description)
                ? (trim($description) === '' ? null : trim($description))
                : $description,
        ]);
    }
}
