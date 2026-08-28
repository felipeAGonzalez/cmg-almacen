<?php

namespace App\Http\Requests;

use App\Models\Cabinet;
use App\Models\InventoryItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return $this->inventoryRules();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    protected function inventoryRules(?InventoryItem $ignoredItem = null): array
    {
        $stockable = $this->stockable();
        $productRule = Rule::unique('inventory_items', 'product_id')
            ->where(fn (Builder $query): Builder => $query
                ->where('stockable_type', $stockable->getMorphClass())
                ->where('stockable_id', $stockable->getKey()));

        if ($ignoredItem !== null) {
            $productRule->ignore($ignoredItem);
        }

        $locationRules = $stockable instanceof Cabinet
            ? ['prohibited']
            : [
                'nullable',
                'integer',
                Rule::exists('locations', 'id')->where(
                    fn (Builder $query): Builder => $query->where('warehouse_id', $stockable->getKey()),
                ),
            ];

        return [
            'product_id' => ['required', 'integer', 'exists:products,id', $productRule],
            'location_id' => $locationRules,
            'minimum_stock' => ['required', 'numeric', 'min:0'],
            'maximum_stock' => ['required', 'numeric', 'gt:minimum_stock'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'product_id.required' => 'El producto es obligatorio.',
            'product_id.exists' => 'El producto seleccionado no existe.',
            'product_id.unique' => 'Este producto ya está registrado en este inventario.',
            'location_id.exists' => 'La ubicación seleccionada no pertenece a este almacén.',
            'location_id.prohibited' => 'No se puede asignar una ubicación al inventario de un gabinete.',
            'minimum_stock.required' => 'La existencia mínima es obligatoria.',
            'minimum_stock.numeric' => 'La existencia mínima debe ser numérica.',
            'minimum_stock.min' => 'La existencia mínima no puede ser negativa.',
            'maximum_stock.required' => 'La existencia máxima es obligatoria.',
            'maximum_stock.numeric' => 'La existencia máxima debe ser numérica.',
            'maximum_stock.gt' => 'La existencia máxima debe ser mayor que la existencia mínima.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['product_id' => 'producto', 'location_id' => 'ubicación', 'minimum_stock' => 'existencia mínima', 'maximum_stock' => 'existencia máxima'];
    }

    protected function stockable(): Model
    {
        return $this->route('cabinet') ?? $this->route('warehouse');
    }
}
