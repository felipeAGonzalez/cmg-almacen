<?php

namespace App\Models;

use App\Enums\InventoryAdjustmentReason;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_batch_id',
        'previous_quantity',
        'counted_quantity',
        'difference',
        'reason',
        'notes',
        'adjusted_by',
        'adjusted_at',
    ];

    protected function casts(): array
    {
        return [
            'previous_quantity' => 'decimal:3',
            'counted_quantity' => 'decimal:3',
            'difference' => 'decimal:3',
            'reason' => InventoryAdjustmentReason::class,
            'adjusted_at' => 'datetime',
        ];
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function inventoryBatch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class);
    }

    public function adjustedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }
}
