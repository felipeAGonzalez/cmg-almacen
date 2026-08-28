<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCabinetRequest extends FormRequest
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
                Rule::unique('cabinets', 'name')
                    ->where(fn (Builder $query): Builder => $query->where('warehouse_id', $warehouse->getKey()))
                    ->ignore($this->route('cabinet')),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return (new StoreCabinetRequest)->messages();
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return (new StoreCabinetRequest)->attributes();
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
