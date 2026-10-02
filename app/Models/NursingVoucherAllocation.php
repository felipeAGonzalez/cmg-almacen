<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NursingVoucherAllocation extends Model
{
    protected $fillable = [
        'inventory_batch_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    public function fulfillmentItem(): BelongsTo
    {
        return $this->belongsTo(NursingVoucherFulfillmentItem::class, 'nursing_voucher_fulfillment_item_id');
    }

    public function inventoryBatch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class);
    }

    public function returnItems(): HasMany
    {
        return $this->hasMany(NursingVoucherReturnItem::class);
    }
}
