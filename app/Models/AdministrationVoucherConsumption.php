<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdministrationVoucherConsumption extends Model
{
    public const NURSING_ALLOCATION = 'nursing_allocation';

    public const MANUAL_OUTBOUND_ALLOCATION = 'manual_outbound_allocation';

    protected $fillable = [
        'source_type',
        'source_id',
        'product_id',
        'quantity',
        'consumption_date',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'consumption_date' => 'date',
        ];
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(AdministrationVoucher::class, 'administration_voucher_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
