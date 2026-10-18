<?php

namespace App\Http\Requests;

use App\Enums\InventoryOutboundReason;
use App\Enums\ManualVoucherRequestingArea;
use App\Models\Cabinet;
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
            'source_cabinet_id' => [
                Rule::prohibitedIf(! $this->user()?->isAdmin()),
                'nullable',
                'integer',
                Rule::exists('cabinets', 'id')->where(fn (Builder $query) => $query->where('warehouse_id', $warehouse->id)),
            ],
            'reason' => ['required', Rule::enum(InventoryOutboundReason::class)],
            'processed_at' => [
                'nullable',
                'date_format:Y-m-d\TH:i',
                'before_or_equal:'.now()->format('Y-m-d\TH:i'),
            ],
            'patient_name' => [
                Rule::requiredIf($this->input('reason') === InventoryOutboundReason::PATIENT_CARE->value),
                'nullable',
                'string',
                'max:255',
            ],
            'room_number' => ['nullable', 'string', 'max:50'],
            'requesting_area' => [
                Rule::requiredIf($this->input('reason') === InventoryOutboundReason::PATIENT_CARE->value),
                'nullable',
                Rule::enum(ManualVoucherRequestingArea::class),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_item_id' => [
                'required', 'integer', 'distinct',
                Rule::exists('inventory_items', 'id')->where(fn (Builder $query) => $query
                    ->where('stockable_type', $this->sourceStockable()->getMorphClass())
                    ->where('stockable_id', $this->sourceStockable()->getKey())),
            ],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'El motivo es obligatorio.',
            'reason.enum' => 'El motivo seleccionado no es válido.',
            'source_cabinet_id.prohibited' => 'Sólo Administración puede registrar una salida desde un gabinete.',
            'source_cabinet_id.exists' => 'El gabinete seleccionado no pertenece a este almacén.',
            'processed_at.date_format' => 'La fecha y hora de salida no tiene un formato válido.',
            'processed_at.before_or_equal' => 'La fecha y hora de salida no puede estar en el futuro.',
            'patient_name.required' => 'El nombre del paciente es obligatorio para registrar un vale manual.',
            'patient_name.max' => 'El nombre del paciente no debe exceder 255 caracteres.',
            'room_number.max' => 'La habitación no debe exceder 50 caracteres.',
            'requesting_area.required' => 'El área solicitante es obligatoria para registrar un vale manual.',
            'requesting_area.enum' => 'El área solicitante seleccionada no es válida.',
            'items.required' => 'Debes agregar al menos un producto.',
            'items.min' => 'Debes agregar al menos un producto.',
            'items.*.inventory_item_id.required' => 'El producto es obligatorio.',
            'items.*.inventory_item_id.exists' => 'El producto no pertenece al origen de inventario seleccionado.',
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

        $normalized['requesting_area'] = match (mb_strtolower((string) $normalized['requesting_area'])) {
            'enfermería', 'enfermeria', 'área de enfermería', 'area de enfermeria' => ManualVoucherRequestingArea::NURSING_AREA->value,
            'pamedic' => ManualVoucherRequestingArea::PAMEDIC->value,
            default => $normalized['requesting_area'],
        };

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

    private function sourceStockable(): Warehouse|Cabinet
    {
        $warehouse = $this->warehouse();

        if (! $this->user()?->isAdmin() || ! $this->filled('source_cabinet_id')) {
            return $warehouse;
        }

        return $warehouse->cabinets()->find($this->integer('source_cabinet_id')) ?? $warehouse;
    }
}
