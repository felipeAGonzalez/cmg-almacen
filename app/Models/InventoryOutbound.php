<?php

namespace App\Models;

use App\Enums\InventoryOutboundReason;
use App\Enums\ManualVoucherRequestingArea;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryOutbound extends Model
{
    use HasFactory;

    public const SOURCE_MANUAL = 'manual';

    protected $fillable = [
        'source_cabinet_id',
        'reason',
        'patient_name',
        'room_number',
        'requesting_area',
        'notes',
        'processed_by',
        'processed_at',
        'source_type',
        'source_id',
    ];

    protected function casts(): array
    {
        return [
            'reason' => InventoryOutboundReason::class,
            'processed_at' => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function sourceCabinet(): BelongsTo
    {
        return $this->belongsTo(Cabinet::class, 'source_cabinet_id');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryOutboundItem::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(InventoryOutboundReturn::class);
    }

    public function requestingAreaLabel(): string
    {
        return ManualVoucherRequestingArea::tryFrom((string) $this->requesting_area)?->label()
            ?? $this->requesting_area
            ?? 'No indicada';
    }

    public function isPamedicVoucher(): bool
    {
        return $this->requesting_area === ManualVoucherRequestingArea::PAMEDIC->value;
    }
}
