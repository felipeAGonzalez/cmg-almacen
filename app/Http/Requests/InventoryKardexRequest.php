<?php

namespace App\Http\Requests;

use App\Models\Cabinet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryKardexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $stockable = $this->route('cabinet') instanceof Cabinet ? $this->route('cabinet') : $this->route('warehouse');
        $movementTypes = $stockable instanceof Cabinet
            ? ['transfer_in', 'adjustment_in', 'adjustment_out']
            : ['entry', 'transfer_out', 'manual_outbound', 'adjustment_in', 'adjustment_out'];

        return [
            'inventory_item_id' => [
                'nullable',
                'integer',
                Rule::exists('inventory_items', 'id')->where(fn ($query) => $query
                    ->where('stockable_type', $stockable->getMorphClass())
                    ->where('stockable_id', $stockable->getKey())),
            ],
            'movement_type' => ['nullable', Rule::in($movementTypes)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }

    public function messages(): array
    {
        return [
            'inventory_item_id.exists' => 'El producto seleccionado no pertenece a este inventario.',
            'movement_type.in' => 'El tipo de movimiento seleccionado no es válido para este inventario.',
            'date_to.after_or_equal' => 'La fecha final debe ser posterior o igual a la fecha inicial.',
        ];
    }

    public function attributes(): array
    {
        return [
            'inventory_item_id' => 'producto',
            'movement_type' => 'tipo de movimiento',
            'date_from' => 'fecha inicial',
            'date_to' => 'fecha final',
        ];
    }
}
