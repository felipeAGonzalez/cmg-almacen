<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class UpdateInventoryItemRequest extends StoreInventoryItemRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return $this->inventoryRules($this->route('inventoryItem'));
    }
}
