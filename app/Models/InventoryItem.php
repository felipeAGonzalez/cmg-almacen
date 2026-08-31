<?php

namespace App\Models;

use Database\Factories\InventoryItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Stores inventory configuration only; physical quantities and status indicators are derived elsewhere.
 */
class InventoryItem extends Model
{
    /** @use HasFactory<InventoryItemFactory> */
    use HasFactory;

    public const STOCK_STATUS_CRITICAL = 'critical';

    public const STOCK_STATUS_LOW = 'low';

    public const STOCK_STATUS_HEALTHY = 'healthy';

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
            'physical_stock' => 'decimal:3',
            'usable_stock' => 'decimal:3',
            'expired_stock' => 'decimal:3',
        ];
    }

    public function scopeWithStockTotals(Builder $query): Builder
    {
        $today = today()->toDateString();

        return $query
            ->withSum(['batches as physical_stock' => fn (Builder $batchQuery) => $batchQuery
                ->where('available_quantity', '>', 0)], 'available_quantity')
            ->withSum(['batches as usable_stock' => fn (Builder $batchQuery) => $batchQuery
                ->where('available_quantity', '>', 0)
                ->where(fn (Builder $expirationQuery) => $expirationQuery
                    ->whereNull('expiration_date')
                    ->orWhereDate('expiration_date', '>=', $today))], 'available_quantity')
            ->withSum(['batches as expired_stock' => fn (Builder $batchQuery) => $batchQuery
                ->where('available_quantity', '>', 0)
                ->whereDate('expiration_date', '<', $today)], 'available_quantity');
    }

    public function physicalStock(): string
    {
        return $this->decimalStockAttribute('physical_stock');
    }

    public function usableStock(): string
    {
        return $this->decimalStockAttribute('usable_stock');
    }

    public function expiredStock(): string
    {
        return $this->decimalStockAttribute('expired_stock');
    }

    public function stockStatus(): string
    {
        if (bccomp($this->usableStock(), $this->minimum_stock, 3) <= 0) {
            return self::STOCK_STATUS_CRITICAL;
        }

        if (bccomp($this->usableStock(), $this->maximum_stock, 3) < 0) {
            return self::STOCK_STATUS_LOW;
        }

        return self::STOCK_STATUS_HEALTHY;
    }

    public function stockStatusLabel(): string
    {
        return match ($this->stockStatus()) {
            self::STOCK_STATUS_CRITICAL => 'Crítico',
            self::STOCK_STATUS_LOW => 'Bajo',
            self::STOCK_STATUS_HEALTHY => 'Correcto',
        };
    }

    public function stockStatusBadgeClass(): string
    {
        return match ($this->stockStatus()) {
            self::STOCK_STATUS_CRITICAL => 'text-bg-danger',
            self::STOCK_STATUS_LOW => 'text-bg-warning',
            self::STOCK_STATUS_HEALTHY => 'text-bg-success',
        };
    }

    public static function formatQuantity(string|int|float|null $quantity): string
    {
        $value = (string) ($quantity ?? '0');

        if (! str_contains($value, '.')) {
            return $value;
        }

        return rtrim(rtrim($value, '0'), '.');
    }

    private function decimalStockAttribute(string $attribute): string
    {
        $value = (string) ($this->getAttribute($attribute) ?? '0');
        [$whole, $decimal] = array_pad(explode('.', $value, 2), 2, '');

        return $whole.'.'.substr(str_pad($decimal, 3, '0'), 0, 3);
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

    /** @return HasMany<EntryItem, $this> */
    public function entryItems(): HasMany
    {
        return $this->hasMany(EntryItem::class);
    }

    /** @return HasMany<InventoryBatch, $this> */
    public function batches(): HasMany
    {
        return $this->hasMany(InventoryBatch::class);
    }

    public function outgoingTransferItems(): HasMany
    {
        return $this->hasMany(InventoryTransferItem::class, 'source_inventory_item_id');
    }

    public function incomingTransferItems(): HasMany
    {
        return $this->hasMany(InventoryTransferItem::class, 'destination_inventory_item_id');
    }

    public function outboundItems(): HasMany
    {
        return $this->hasMany(InventoryOutboundItem::class);
    }
}
