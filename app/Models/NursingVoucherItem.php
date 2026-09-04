<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NursingVoucherItem extends Model
{
    protected $fillable = [
        'product_id',
        'requested_quantity',
        'supplied_quantity',
    ];

    protected function casts(): array
    {
        return [
            'requested_quantity' => 'decimal:3',
            'supplied_quantity' => 'decimal:3',
        ];
    }

    public function pendingQuantity(): string
    {
        return bcsub($this->requested_quantity, $this->supplied_quantity, 3);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(NursingVoucher::class, 'nursing_voucher_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function fulfillmentItems(): HasMany
    {
        return $this->hasMany(NursingVoucherFulfillmentItem::class);
    }
}
