<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'inventory_scope' => ['nullable', 'string', 'regex:/^(warehouse|cabinet):[1-9][0-9]*$/'],
            'stock_status' => ['nullable', Rule::in(['critical', 'low', 'healthy', 'without_expiration'])],
            'expiration_status' => ['nullable', Rule::in(['expired', 'valid', 'without_expiration'])],
            'expiration_from' => ['nullable', 'date_format:Y-m-d'],
            'expiration_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:expiration_from'],
        ];
    }

    public function messages(): array
    {
        return [
            'inventory_scope.regex' => 'El inventario seleccionado no es válido.',
            'expiration_to.after_or_equal' => 'La fecha final de caducidad debe ser igual o posterior a la fecha inicial.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->filled('search') ? trim((string) $this->input('search')) : null,
        ]);
    }
}
