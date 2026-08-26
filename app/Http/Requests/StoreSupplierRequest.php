<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierRequest extends FormRequest
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
        $warehouse = $this->route('warehouse');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('suppliers', 'name')
                    ->where(fn (Builder $query): Builder => $query->where('warehouse_id', $warehouse->getKey())),
            ],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string'],
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
            'name.unique' => 'Ya existe un proveedor con este nombre en el almacén.',
            'contact_name.string' => 'El contacto debe ser texto.',
            'contact_name.max' => 'El contacto no puede tener más de 255 caracteres.',
            'phone.string' => 'El teléfono debe ser texto.',
            'phone.max' => 'El teléfono no puede tener más de 50 caracteres.',
            'email.email' => 'El correo electrónico debe ser válido.',
            'email.max' => 'El correo electrónico no puede tener más de 255 caracteres.',
            'address.string' => 'La dirección debe ser texto.',
            'address.max' => 'La dirección no puede tener más de 1000 caracteres.',
            'notes.string' => 'Las notas deben ser texto.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'contact_name' => 'contacto',
            'phone' => 'teléfono',
            'email' => 'correo electrónico',
            'address' => 'dirección',
            'notes' => 'notas',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->normalizedStrings());
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedStrings(): array
    {
        return collect(['name', 'contact_name', 'phone', 'email', 'address', 'notes'])
            ->mapWithKeys(function (string $field): array {
                $value = $this->input($field);

                if (! is_string($value)) {
                    return [$field => $value];
                }

                $value = trim($value);

                return [$field => $value === '' ? null : $value];
            })
            ->all();
    }
}
