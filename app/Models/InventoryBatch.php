<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Operational physical stock created from an EntryItem or derived from a traceable source batch.
 */
class InventoryBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_item_id',
        'entry_item_id',
        'source_batch_id',
        'internal_lot',
        'manufacturer_lot',
        'expiration_date',
        'received_quantity',
        'available_quantity',
        'unit_cost',
    ];

    protected function casts(): array
    {
        return [
            'expiration_date' => 'date',
            'received_quantity' => 'decimal:3',
            'available_quantity' => 'decimal:3',
            'unit_cost' => 'decimal:4',
        ];
    }

    /** @return BelongsTo<InventoryItem, $this> */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    /** @return BelongsTo<EntryItem, $this> */
    public function entryItem(): BelongsTo
    {
        return $this->belongsTo(EntryItem::class);
    }

    public function sourceBatch(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_batch_id');
    }

    public function derivedBatches(): HasMany
    {
        return $this->hasMany(self::class, 'source_batch_id');
    }

    public function outgoingTransferAllocations(): HasMany
    {
        return $this->hasMany(InventoryTransferAllocation::class, 'source_batch_id');
    }

    public function incomingTransferAllocations(): HasMany
    {
        return $this->hasMany(InventoryTransferAllocation::class, 'destination_batch_id');
    }
}
