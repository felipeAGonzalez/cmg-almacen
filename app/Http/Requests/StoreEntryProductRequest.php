<?php

namespace App\Http\Requests;

use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

class StoreEntryProductRequest extends StoreProductRequest
{
    public function rules(): array
    {
        $warehouse = $this->route('warehouse');

        return [
            ...parent::rules(),
            'location_id' => [
                'nullable',
                'integer',
                Rule::exists('locations', 'id')->where(
                    fn (Builder $query): Builder => $query->where('warehouse_id', $warehouse->getKey()),
                ),
            ],
            'minimum_stock' => ['required', 'numeric', 'min:0'],
            'maximum_stock' => ['required', 'numeric', 'gt:minimum_stock'],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'location_id.exists' => 'La ubicación seleccionada no pertenece a este almacén.',
            'minimum_stock.required' => 'La existencia mínima es obligatoria.',
            'minimum_stock.numeric' => 'La existencia mínima debe ser numérica.',
            'minimum_stock.min' => 'La existencia mínima no puede ser negativa.',
            'maximum_stock.required' => 'La existencia máxima es obligatoria.',
            'maximum_stock.numeric' => 'La existencia máxima debe ser numérica.',
            'maximum_stock.gt' => 'La existencia máxima debe ser mayor que la existencia mínima.',
        ];
    }
}
