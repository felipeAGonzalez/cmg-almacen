<?php

namespace App\Http\Requests;

use App\Enums\InventoryOutboundReason;
use App\Models\Warehouse;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryOutboundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $warehouse = $this->warehouse();

        return [
            'reason' => ['required', Rule::enum(InventoryOutboundReason::class)],
            'patient_name' => [
                Rule::requiredIf($this->input('reason') === InventoryOutboundReason::PATIENT_CARE->value),
                'nullable',
                'string',
                'max:255',
            ],
            'room_number' => ['nullable', 'string', 'max:50'],
            'requesting_area' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_item_id' => [
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
            'reason.required' => 'El motivo es obligatorio.',
            'reason.enum' => 'El motivo seleccionado no es válido.',
            'patient_name.required' => 'El nombre del paciente es obligatorio para registrar un vale manual.',
            'patient_name.max' => 'El nombre del paciente no debe exceder 255 caracteres.',
            'room_number.max' => 'La habitación no debe exceder 50 caracteres.',
            'requesting_area.max' => 'El área solicitante no debe exceder 150 caracteres.',
            'items.required' => 'Debes agregar al menos un producto.',
            'items.min' => 'Debes agregar al menos un producto.',
            'items.*.inventory_item_id.required' => 'El producto es obligatorio.',
            'items.*.inventory_item_id.exists' => 'El producto no pertenece al inventario principal de este almacén.',
            'items.*.inventory_item_id.distinct' => 'El producto está repetido en la salida.',
            'items.*.quantity.required' => 'La cantidad es obligatoria.',
            'items.*.quantity.numeric' => 'La cantidad debe ser numérica.',
            'items.*.quantity.gt' => 'La cantidad debe ser mayor que cero.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $fields = ['patient_name', 'room_number', 'requesting_area', 'notes'];
        $normalized = [];

        foreach ($fields as $field) {
            $value = $this->input($field);
            $normalized[$field] = is_string($value) && trim($value) !== '' ? trim($value) : null;
        }

        if ($this->input('reason') !== InventoryOutboundReason::PATIENT_CARE->value) {
            $normalized['patient_name'] = null;
            $normalized['room_number'] = null;
            $normalized['requesting_area'] = null;
        }

        $this->merge($normalized);
    }

    private function warehouse(): Warehouse
    {
        return $this->route('warehouse');
    }
}
