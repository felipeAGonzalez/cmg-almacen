<?php

namespace App\Models;

use App\Exceptions\NursingSupplyConfigurationException;
use Database\Factories\WarehouseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Warehouse extends Model
{
    /** @use HasFactory<WarehouseFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'default_nursing_cabinet_id',
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

    /**  BelongsTo<Cabinet, $this> */
    public function defaultNursingCabinet(): BelongsTo
    {
        return $this->belongsTo(Cabinet::class, 'default_nursing_cabinet_id');
    }

    public function defaultNursingCabinetOrFail(): Cabinet
    {
        $cabinet = $this->defaultNursingCabinet;

        if (! $cabinet || $cabinet->warehouse_id !== $this->getKey()) {
            throw new NursingSupplyConfigurationException('El almacén no tiene configurado un gabinete predeterminado de Enfermería.');
        }

        return $cabinet;
    }

    /** @return MorphMany<InventoryItem, $this> */
    public function inventoryItems(): MorphMany
    {
        return $this->morphMany(InventoryItem::class, 'stockable');
    }

    /** @return HasMany<Entry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    public function inventoryTransfers(): HasMany
    {
        return $this->hasMany(InventoryTransfer::class);
    }

    public function inventoryOutbounds(): HasMany
    {
        return $this->hasMany(InventoryOutbound::class);
    }

    public function nursingVouchers(): HasMany
    {
        return $this->hasMany(NursingVoucher::class);
    }
}
