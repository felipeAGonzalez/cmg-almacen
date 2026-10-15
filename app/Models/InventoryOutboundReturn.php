<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryOutboundReturn extends Model
{
    protected $fillable = ['inventory_outbound_id', 'warehouse_id', 'received_by', 'received_at', 'notes'];

    protected function casts(): array
    {
        return ['received_at' => 'datetime'];
    }

    public function outbound(): BelongsTo
    {
        return $this->belongsTo(InventoryOutbound::class, 'inventory_outbound_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryOutboundReturnItem::class);
    }
}
