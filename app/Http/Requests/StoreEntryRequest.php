<?php

namespace App\Http\Requests;

use App\Models\InventoryItem;
use App\Models\Warehouse;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $warehouse = $this->warehouse();

        return [
            'supplier_id' => [
                'required',
                'integer',
                Rule::exists('suppliers', 'id')->where(
                    fn (Builder $query): Builder => $query->where('warehouse_id', $warehouse->getKey()),
                ),
            ],
            'invoice_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('entries', 'invoice_number')->where(
                    fn (Builder $query): Builder => $query
                        ->where('warehouse_id', $warehouse->getKey())
                        ->where('supplier_id', $this->input('supplier_id')),
                ),
            ],
            'invoice_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_item_id' => [
                'required',
                'integer',
                Rule::exists('inventory_items', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('stockable_type', $warehouse->getMorphClass())
                        ->where('stockable_id', $warehouse->getKey()),
                ),
            ],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.manufacturer_lot' => ['nullable', 'string', 'max:255'],
            'items.*.expiration_date' => ['nullable', 'date'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $items = $this->input('items');

            if (! is_array($items)) {
                return;
            }

            $inventoryItems = InventoryItem::query()
                ->with('product:id,requires_expiration')
                ->whereIn('id', collect($items)->pluck('inventory_item_id')->filter())
                ->get()
                ->keyBy('id');

            foreach ($items as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }

                $inventoryItem = $inventoryItems->get($item['inventory_item_id'] ?? null);
                if ($inventoryItem?->product?->requires_expiration && empty($item['expiration_date'])) {
                    $validator->errors()->add(
                        "items.$index.expiration_date",
                        'La fecha de caducidad es obligatoria para este producto.',
                    );
                }
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'supplier_id.required' => 'El proveedor es obligatorio.',
            'supplier_id.exists' => 'El proveedor seleccionado no pertenece a este almacén.',
            'invoice_number.required' => 'El número de factura es obligatorio.',
            'invoice_number.unique' => 'Esta factura ya está registrada para el proveedor seleccionado.',
            'invoice_date.required' => 'La fecha de factura es obligatoria.',
            'invoice_date.date' => 'La fecha de factura no es válida.',
            'items.required' => 'Debes agregar al menos una partida.',
            'items.array' => 'Las partidas no son válidas.',
            'items.min' => 'Debes agregar al menos una partida.',
            'items.*.inventory_item_id.required' => 'El producto configurado es obligatorio.',
            'items.*.inventory_item_id.exists' => 'El producto no pertenece al inventario principal de este almacén.',
            'items.*.quantity.required' => 'La cantidad recibida es obligatoria.',
            'items.*.quantity.numeric' => 'La cantidad recibida debe ser numérica.',
            'items.*.quantity.gt' => 'La cantidad recibida debe ser mayor que cero.',
            'items.*.unit_cost.required' => 'El costo unitario es obligatorio.',
            'items.*.unit_cost.numeric' => 'El costo unitario debe ser numérico.',
            'items.*.unit_cost.min' => 'El costo unitario no puede ser negativo.',
            'items.*.manufacturer_lot.max' => 'El lote del fabricante no puede tener más de 255 caracteres.',
            'items.*.expiration_date.date' => 'La fecha de caducidad no es válida.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'invoice_number' => $this->trimmed('invoice_number'),
            'notes' => $this->nullableTrimmed('notes'),
            'items' => collect($this->input('items', []))->map(function (mixed $item): mixed {
                if (! is_array($item)) {
                    return $item;
                }

                $item['manufacturer_lot'] = $this->nullableTrimmedValue($item['manufacturer_lot'] ?? null);
                $item['expiration_date'] = $this->nullableTrimmedValue($item['expiration_date'] ?? null);

                return $item;
            })->all(),
        ]);
    }

    private function warehouse(): Warehouse
    {
        return $this->route('warehouse');
    }

    private function trimmed(string $field): mixed
    {
        $value = $this->input($field);

        return is_string($value) ? trim($value) : $value;
    }

    private function nullableTrimmed(string $field): mixed
    {
        return $this->nullableTrimmedValue($this->input($field));
    }

    private function nullableTrimmedValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
