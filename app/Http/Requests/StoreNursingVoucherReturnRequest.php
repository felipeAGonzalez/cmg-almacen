<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreNursingVoucherReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('requestReturn', $this->route('nursingVoucher')) ?? false;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.voucher_item_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0', 'decimal:0,3'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Selecciona al menos un producto para devolver.',
            'items.*.voucher_item_id.distinct' => 'No repitas productos en la devolución.',
            'items.*.quantity.numeric' => 'La cantidad a devolver debe ser numérica.',
            'items.*.quantity.min' => 'La cantidad a devolver no puede ser negativa.',
            'items.*.quantity.decimal' => 'La cantidad a devolver admite hasta 3 decimales.',
        ];
    }
}
