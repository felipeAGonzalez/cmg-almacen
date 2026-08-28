<?php

namespace App\Models;

use Database\Factories\WarehouseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Warehouse extends Model
{
    /** @use HasFactory<WarehouseFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * @return HasMany<Supplier, $this>
     */
    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    /** @return HasMany<Location, $this> */
    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    /** @return HasMany<Cabinet, $this> */
    public function cabinets(): HasMany
    {
        return $this->hasMany(Cabinet::class);
    }

    /** @return MorphMany<InventoryItem, $this> */
    public function inventoryItems(): MorphMany
    {
        return $this->morphMany(InventoryItem::class, 'stockable');
    }
}
