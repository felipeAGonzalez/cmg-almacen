<?php

namespace App\Models;

use App\Enums\AdministrationVoucherStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdministrationVoucher extends Model
{
    protected $fillable = ['requested_by', 'warehouse_id', 'cabinet_id', 'status', 'requested_at', 'completed_at', 'cancelled_at', 'rejected_at', 'cancelled_by', 'rejected_by', 'rejection_reason', 'notes'];

    protected function casts(): array
    {
        return [
            'status' => AdministrationVoucherStatus::class,
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

    public function cabinet(): BelongsTo
    {
        return $this->belongsTo(Cabinet::class);
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
        return $this->hasMany(AdministrationVoucherItem::class);
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(InventoryTransfer::class);
    }
}
