<?php

namespace App\Models;

use App\Enums\NursingSupplySourceType;
use App\Enums\NursingVoucherStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NursingVoucher extends Model
{
    protected $fillable = [
        'requested_by',
        'warehouse_id',
        'source_type',
        'source_cabinet_id',
        'external_patient_id',
        'external_hospitalization_id',
        'patient_name',
        'external_room_id',
        'room_number',
        'status',
        'requested_at',
        'completed_at',
        'cancelled_at',
        'rejected_at',
        'cancelled_by',
        'rejected_by',
        'rejection_reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'source_type' => NursingSupplySourceType::class,
            'status' => NursingVoucherStatus::class,
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function sourceCabinet(): BelongsTo
    {
        return $this->belongsTo(Cabinet::class, 'source_cabinet_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(NursingVoucherItem::class);
    }

    public function fulfillments(): HasMany
    {
        return $this->hasMany(NursingVoucherFulfillment::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(NursingVoucherReturn::class);
    }
}
