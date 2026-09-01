<?php

namespace App\Http\Requests;

use App\Enums\InventoryAdjustmentReason;
use App\Models\InventoryItem;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $inventoryItem = $this->route('inventoryItem');

        return [
            'inventory_batch_id' => [
                'required',
                'integer',
                Rule::exists('inventory_batches', 'id')->where(fn (Builder $query) => $query
                    ->where('inventory_item_id', $inventoryItem instanceof InventoryItem ? $inventoryItem->id : 0)),
            ],
            'counted_quantity' => ['required', 'numeric', 'decimal:0,3', 'gte:0'],
            'reason' => ['required', Rule::enum(InventoryAdjustmentReason::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'inventory_batch_id.required' => 'El lote es obligatorio.',
            'inventory_batch_id.exists' => 'El lote seleccionado no pertenece a este producto.',
            'counted_quantity.required' => 'La cantidad contada es obligatoria.',
            'counted_quantity.numeric' => 'La cantidad contada debe ser numérica.',
            'counted_quantity.gte' => 'La cantidad contada debe ser mayor o igual a cero.',
            'reason.required' => 'El motivo es obligatorio.',
            'reason.enum' => 'El motivo seleccionado no es válido.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $notes = $this->input('notes');
        $this->merge(['notes' => is_string($notes) && trim($notes) !== '' ? trim($notes) : null]);
    }
}
