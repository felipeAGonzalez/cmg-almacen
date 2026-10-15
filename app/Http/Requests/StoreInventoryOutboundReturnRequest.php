<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInventoryOutboundReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'inventory_outbound_id' => ['required', 'integer', 'exists:inventory_outbounds,id'],
            'received_at' => ['required', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.outbound_item_id' => ['required', 'integer', 'distinct', 'exists:inventory_outbound_items,id'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0', 'decimal:0,3'],
        ];
    }

    public function messages(): array
    {
        return [
            'inventory_outbound_id.required' => 'Selecciona la salida original.',
            'received_at.required' => 'La fecha de devolución es obligatoria.',
            'received_at.before_or_equal' => 'La fecha de devolución no puede estar en el futuro.',
            'items.required' => 'Selecciona al menos un producto para devolver.',
            'items.*.outbound_item_id.distinct' => 'No puedes repetir un producto de la salida.',
            'items.*.quantity.numeric' => 'La cantidad debe ser numérica.',
            'items.*.quantity.decimal' => 'La cantidad admite como máximo 3 decimales.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $notes = $this->input('notes');
        $this->merge(['notes' => is_string($notes) && trim($notes) !== '' ? trim($notes) : null]);
    }
}
