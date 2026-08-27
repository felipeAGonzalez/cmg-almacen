<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitRequest extends FormRequest
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
        $unit = $this->route('unit');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('units', 'name')->ignore($unit),
            ],
            'abbreviation' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('units', 'abbreviation')->ignore($unit),
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
            'name.unique' => 'Ya existe una unidad con este nombre.',
            'abbreviation.string' => 'La abreviatura debe ser texto.',
            'abbreviation.max' => 'La abreviatura no puede tener más de 20 caracteres.',
            'abbreviation.unique' => 'Ya existe una unidad con esta abreviatura.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'abbreviation' => 'abreviatura',
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        $abbreviation = $this->input('abbreviation');

        $this->merge([
            'name' => is_string($name) ? trim($name) : $name,
            'abbreviation' => is_string($abbreviation)
                ? (trim($abbreviation) === '' ? null : trim($abbreviation))
                : $abbreviation,
        ]);
    }
}
