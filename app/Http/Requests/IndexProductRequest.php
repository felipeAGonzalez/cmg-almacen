<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
        ];
    }

    protected function prepareForValidation(): void
    {
        $search = $this->input('search');
        $categoryId = $this->input('category_id');

        $this->merge([
            'search' => is_string($search) && trim($search) !== '' ? trim($search) : null,
            'category_id' => is_numeric($categoryId) ? (int) $categoryId : null,
        ]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'category_id.exists' => 'La categoría seleccionada no existe.',
        ];
    }
}
