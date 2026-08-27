<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100', 'required_without:barcode', Rule::unique('products', 'code')->ignore($product)],
            'barcode' => ['nullable', 'string', 'max:100', 'required_without:code', Rule::unique('products', 'barcode')->ignore($product)],
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return (new StoreProductRequest)->messages();
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return (new StoreProductRequest)->attributes();
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
