<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NursingVoucherFulfillmentItem extends Model
{
    protected $fillable = [
        'nursing_voucher_item_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(NursingVoucherFulfillment::class, 'nursing_voucher_fulfillment_id');
    }

    public function voucherItem(): BelongsTo
    {
        return $this->belongsTo(NursingVoucherItem::class, 'nursing_voucher_item_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(NursingVoucherAllocation::class);
    }
}
