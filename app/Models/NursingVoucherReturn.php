<?php

namespace App\Models;

use App\Enums\NursingVoucherReturnStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NursingVoucherReturn extends Model
{
    protected $fillable = [
        'nursing_voucher_id',
        'requested_by',
        'received_by',
        'rejected_by',
        'cancelled_by',
        'status',
        'requested_at',
        'received_at',
        'rejected_at',
        'cancelled_at',
        'notes',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => NursingVoucherReturnStatus::class,
            'requested_at' => 'datetime',
            'received_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(NursingVoucher::class, 'nursing_voucher_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(NursingVoucherReturnItem::class);
    }
}
