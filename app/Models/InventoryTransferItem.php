<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryTransferItem extends Model
{
    use HasFactory;

    protected $fillable = ['source_inventory_item_id', 'destination_inventory_item_id', 'requested_quantity'];

    protected function casts(): array
    {
        return ['requested_quantity' => 'decimal:3'];
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(InventoryTransfer::class, 'inventory_transfer_id');
    }

    public function sourceInventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'source_inventory_item_id');
    }

    public function destinationInventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'destination_inventory_item_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(InventoryTransferAllocation::class);
    }
}
