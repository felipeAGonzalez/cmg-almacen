<?php

namespace App\Models;

use App\Enums\UserRole;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use LogicException;

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
        'hospital_user_id',
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

    public function canCreateAdministrationVoucher(?CarbonInterface $at = null): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($this->role !== UserRole::WAREHOUSE_MANAGER) {
            return false;
        }

        $setting = OperationalSetting::current();
        $localDateTime = ($at ?? now())->copy()->setTimezone(config('app.timezone'));

        return $setting->administration_voucher_delegate_user_id === $this->getKey()
            && $setting->administration_voucher_delegate_day === $localDateTime->dayOfWeekIso;
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

    public function warehouseForNursing(): ?Warehouse
    {
        $warehouses = $this->warehouses()->limit(2)->get();

        if ($warehouses->count() > 1) {
            throw new LogicException('A nurse cannot be assigned to more than one warehouse.');
        }

        return $warehouses->first();
    }

    public function inventoryTransfers(): HasMany
    {
        return $this->hasMany(InventoryTransfer::class, 'transferred_by');
    }

    public function inventoryOutbounds(): HasMany
    {
        return $this->hasMany(InventoryOutbound::class, 'processed_by');
    }

    public function inventoryAdjustments(): HasMany
    {
        return $this->hasMany(InventoryAdjustment::class, 'adjusted_by');
    }

    public function requestedNursingVouchers(): HasMany
    {
        return $this->hasMany(NursingVoucher::class, 'requested_by');
    }

    public function nursingVoucherFulfillments(): HasMany
    {
        return $this->hasMany(NursingVoucherFulfillment::class, 'supplied_by');
    }
}
