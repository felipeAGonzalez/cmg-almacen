<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdministrationVoucherFulfillmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.voucher_item_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $notes = $this->input('notes');
        $items = collect($this->input('items', []))->filter(fn ($item) => is_array($item) && isset($item['quantity']) && is_numeric($item['quantity']) && bccomp((string) $item['quantity'], '0', 3) > 0)->values()->all();
        $this->merge(['notes' => is_string($notes) && trim($notes) !== '' ? trim($notes) : null, 'items' => $items]);
    }
}
