<?php

namespace App\Http\Requests;

use App\Models\Warehouse;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $warehouse = $this->warehouse();

        return [
            'cabinet_id' => ['required', 'integer', Rule::exists('cabinets', 'id')->where(fn (Builder $query) => $query->where('warehouse_id', $warehouse->id))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.source_inventory_item_id' => [
                'required', 'integer', 'distinct',
                Rule::exists('inventory_items', 'id')->where(fn (Builder $query) => $query
                    ->where('stockable_type', $warehouse->getMorphClass())
                    ->where('stockable_id', $warehouse->id)),
            ],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'cabinet_id.required' => 'El gabinete es obligatorio.',
            'cabinet_id.exists' => 'El gabinete seleccionado no pertenece a este almacén.',
            'items.required' => 'Debes agregar al menos un producto.',
            'items.min' => 'Debes agregar al menos un producto.',
            'items.*.source_inventory_item_id.required' => 'El producto es obligatorio.',
            'items.*.source_inventory_item_id.exists' => 'El producto no pertenece al inventario principal de este almacén.',
            'items.*.source_inventory_item_id.distinct' => 'El producto está repetido en la transferencia.',
            'items.*.quantity.required' => 'La cantidad es obligatoria.',
            'items.*.quantity.numeric' => 'La cantidad debe ser numérica.',
            'items.*.quantity.gt' => 'La cantidad debe ser mayor que cero.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $notes = $this->input('notes');
        $this->merge(['notes' => is_string($notes) && trim($notes) !== '' ? trim($notes) : null]);
    }

    private function warehouse(): Warehouse
    {
        return $this->route('warehouse');
    }
}
