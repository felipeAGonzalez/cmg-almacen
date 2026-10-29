<?php

namespace App\Http\Requests;

use App\Enums\InventoryOutboundReason;
use App\Enums\ManualVoucherRequestingArea;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexInventoryOutboundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $warehouse = $this->route('warehouse');

        return [
            'search' => ['nullable', 'string', 'max:150'],
            'reason' => ['nullable', Rule::enum(InventoryOutboundReason::class)],
            'source' => ['nullable', Rule::in(['warehouse', 'cabinet'])],
            'cabinet_id' => [
                'nullable',
                'integer',
                Rule::exists('cabinets', 'id')->where(fn ($query) => $query->where('warehouse_id', $warehouse?->id)),
            ],
            'requesting_area' => ['nullable', Rule::enum(ManualVoucherRequestingArea::class)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $search = $this->input('search');

        $this->merge([
            'search' => is_string($search) && trim($search) !== '' ? trim($search) : null,
        ]);
    }
}
