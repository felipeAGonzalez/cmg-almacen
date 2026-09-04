<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NursingVoucherFulfillment extends Model
{
    protected $fillable = [
        'supplied_by',
        'supplied_at',
        'notes',
    ];

    protected function casts(): array
    {
        return ['supplied_at' => 'datetime'];
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(NursingVoucher::class, 'nursing_voucher_id');
    }

    public function suppliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supplied_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(NursingVoucherFulfillmentItem::class);
    }
}
