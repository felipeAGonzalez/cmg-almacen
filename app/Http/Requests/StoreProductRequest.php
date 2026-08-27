<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100', 'required_without:barcode', 'unique:products,code'],
            'barcode' => ['nullable', 'string', 'max:100', 'required_without:code', 'unique:products,barcode'],
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'name.string' => 'El nombre debe ser texto.',
            'name.max' => 'El nombre no puede tener más de 255 caracteres.',
            'code.required_without' => 'Debes capturar un código interno o un código de barras.',
            'code.string' => 'El código interno debe ser texto.',
            'code.max' => 'El código interno no puede tener más de 100 caracteres.',
            'barcode.required_without' => 'Debes capturar un código interno o un código de barras.',
            'barcode.string' => 'El código de barras debe ser texto.',
            'barcode.max' => 'El código de barras no puede tener más de 100 caracteres.',
            'code.unique' => 'El código interno ya está registrado.',
            'barcode.unique' => 'El código de barras ya está registrado.',
            'unit_id.required' => 'La unidad es obligatoria.',
            'unit_id.integer' => 'La unidad seleccionada no es válida.',
            'unit_id.exists' => 'La unidad seleccionada no existe.',
            'category_id.required' => 'La categoría es obligatoria.',
            'category_id.integer' => 'La categoría seleccionada no es válida.',
            'category_id.exists' => 'La categoría seleccionada no existe.',
            'brand_id.required' => 'La marca es obligatoria.',
            'brand_id.integer' => 'La marca seleccionada no es válida.',
            'brand_id.exists' => 'La marca seleccionada no existe.',
            'description.string' => 'La descripción debe ser texto.',
            'description.max' => 'La descripción no puede tener más de 2000 caracteres.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nombre', 'code' => 'código interno', 'barcode' => 'código de barras',
            'unit_id' => 'unidad', 'category_id' => 'categoría', 'brand_id' => 'marca',
            'description' => 'descripción',
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'code', 'barcode', 'description'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $value = trim($value);
                $this->merge([$field => $field !== 'name' && $value === '' ? null : $value]);
            }
        }
    }
}
