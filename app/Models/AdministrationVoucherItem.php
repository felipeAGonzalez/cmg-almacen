<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdministrationVoucherItem extends Model
{
    protected $fillable = ['product_id', 'requested_quantity', 'supplied_quantity'];

    protected function casts(): array
    {
        return ['requested_quantity' => 'decimal:3', 'supplied_quantity' => 'decimal:3'];
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(AdministrationVoucher::class, 'administration_voucher_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function pendingQuantity(): string
    {
        return bcsub($this->requested_quantity, $this->supplied_quantity, 3);
    }
}
