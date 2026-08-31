<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'last_name_one',
        'last_name_two',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, [UserRole::ADMINISTRATOR, UserRole::ROOT], true);
    }

    public function canManageSuppliersIn(Warehouse $warehouse): bool
    {
        return $this->canManageWarehouse($warehouse);
    }

    public function canManageWarehouse(Warehouse $warehouse): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->role === UserRole::WAREHOUSE_MANAGER
            && $this->warehouses()->whereKey($warehouse->getKey())->exists();
    }

    /**
     * @return BelongsToMany<Warehouse, $this>
     */
    public function warehouses(): BelongsToMany
    {
        return $this->belongsToMany(Warehouse::class);
    }

    public function inventoryTransfers(): HasMany
    {
        return $this->hasMany(InventoryTransfer::class, 'transferred_by');
    }

    public function inventoryOutbounds(): HasMany
    {
        return $this->hasMany(InventoryOutbound::class, 'processed_by');
    }
}
