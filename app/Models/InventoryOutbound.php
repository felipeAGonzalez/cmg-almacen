<?php

namespace App\Models;

use App\Enums\InventoryOutboundReason;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryOutbound extends Model
{
    use HasFactory;

    public const SOURCE_MANUAL = 'manual';

    protected $fillable = [
        'reason',
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

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryOutboundItem::class);
    }
}
