<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NursingVoucherReturnItem extends Model
{
    protected $fillable = [
        'nursing_voucher_allocation_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    public function nursingReturn(): BelongsTo
    {
        return $this->belongsTo(NursingVoucherReturn::class, 'nursing_voucher_return_id');
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(NursingVoucherAllocation::class, 'nursing_voucher_allocation_id');
    }
}
