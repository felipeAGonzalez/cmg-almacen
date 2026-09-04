<?php

namespace App\Http\Requests;

use App\Models\NursingVoucher;
use Illuminate\Foundation\Http\FormRequest;

class StoreNursingVoucherFulfillmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $voucher = $this->route('nursingVoucher');

        return $voucher instanceof NursingVoucher
            && $this->user()?->can('fulfill', $voucher) === true;
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.voucher_item_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3', 'max:999999999999.999'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Debes indicar al menos un producto para surtir.',
            'items.*.voucher_item_id.distinct' => 'El producto está repetido en el surtido.',
            'items.*.quantity.gt' => 'La cantidad a surtir debe ser mayor que cero.',
            'items.*.quantity.decimal' => 'La cantidad a surtir puede tener hasta tres decimales.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $items = collect((array) $this->input('items', []))
            ->filter(function ($item): bool {
                if (! is_array($item)) {
                    return true;
                }

                $quantity = trim((string) ($item['quantity'] ?? ''));

                return $quantity !== '' && (! is_numeric($quantity) || bccomp($quantity, '0', 3) !== 0);
            })
            ->values()
            ->all();

        $this->merge([
            'notes' => filled($this->input('notes')) ? trim((string) $this->input('notes')) : null,
            'items' => $items,
        ]);
    }
}
