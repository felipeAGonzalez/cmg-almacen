<?php

namespace App\Http\Requests;

use App\Models\NursingVoucher;
use Illuminate\Foundation\Http\FormRequest;

class StoreNursingVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', NursingVoucher::class) === true;
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3', 'max:999999999999.999'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Debes agregar al menos un producto.',
            'items.min' => 'Debes agregar al menos un producto.',
            'items.*.product_id.distinct' => 'El producto está repetido en el vale.',
            'items.*.quantity.gt' => 'La cantidad solicitada debe ser mayor que cero.',
            'items.*.quantity.decimal' => 'La cantidad solicitada puede tener hasta tres decimales.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'notes' => filled($this->input('notes')) ? trim((string) $this->input('notes')) : null,
        ]);
    }
}
