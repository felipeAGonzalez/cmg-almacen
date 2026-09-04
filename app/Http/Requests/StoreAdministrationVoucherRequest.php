<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdministrationVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'cabinet_id' => ['required', 'integer', Rule::exists('cabinets', 'id')->where(fn ($query) => $query->where('warehouse_id', $this->integer('warehouse_id')))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'warehouse_id.required' => 'El almacén es obligatorio.',
            'cabinet_id.required' => 'El gabinete es obligatorio.',
            'cabinet_id.exists' => 'El gabinete debe pertenecer al almacén seleccionado.',
            'items.required' => 'Debes agregar al menos un producto.',
            'items.min' => 'Debes agregar al menos un producto.',
            'items.*.product_id.distinct' => 'El producto está repetido en el vale.',
            'items.*.quantity.gt' => 'La cantidad debe ser mayor que cero.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $notes = $this->input('notes');
        $this->merge(['notes' => is_string($notes) && trim($notes) !== '' ? trim($notes) : null]);
    }
}
