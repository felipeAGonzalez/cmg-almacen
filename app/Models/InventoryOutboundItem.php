<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryOutboundItem extends Model
{
    use HasFactory;

    protected $fillable = ['inventory_item_id', 'requested_quantity'];

    protected function casts(): array
    {
        return ['requested_quantity' => 'decimal:3'];
    }

    public function outbound(): BelongsTo
    {
        return $this->belongsTo(InventoryOutbound::class, 'inventory_outbound_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(InventoryOutboundAllocation::class);
    }
}
