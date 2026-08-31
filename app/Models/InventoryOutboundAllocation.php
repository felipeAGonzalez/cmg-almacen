<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryOutboundAllocation extends Model
{
    use HasFactory;

    protected $fillable = ['inventory_batch_id', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    public function outboundItem(): BelongsTo
    {
        return $this->belongsTo(InventoryOutboundItem::class, 'inventory_outbound_item_id');
    }

    public function inventoryBatch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class);
    }
}
