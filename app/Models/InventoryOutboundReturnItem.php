<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryOutboundReturnItem extends Model
{
    protected $fillable = ['inventory_outbound_allocation_id', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    public function inventoryReturn(): BelongsTo
    {
        return $this->belongsTo(InventoryOutboundReturn::class, 'inventory_outbound_return_id');
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(InventoryOutboundAllocation::class, 'inventory_outbound_allocation_id');
    }
}
