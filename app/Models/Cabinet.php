<?php

namespace App\Models;

use Database\Factories\CabinetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Cabinet extends Model
{
    /** @use HasFactory<CabinetFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
    ];

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return MorphMany<InventoryItem, $this> */
    public function inventoryItems(): MorphMany
    {
        return $this->morphMany(InventoryItem::class, 'stockable');
    }
}
