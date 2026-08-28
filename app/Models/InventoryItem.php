<?php

namespace App\Models;

use Database\Factories\InventoryItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Stores inventory configuration only; physical quantities and status indicators are derived elsewhere.
 */
class InventoryItem extends Model
{
    /** @use HasFactory<InventoryItemFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'location_id',
        'minimum_stock',
        'maximum_stock',
    ];

    protected function casts(): array
    {
        return [
            'minimum_stock' => 'decimal:3',
            'maximum_stock' => 'decimal:3',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function stockable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
