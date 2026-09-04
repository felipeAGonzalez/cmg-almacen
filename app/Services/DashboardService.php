<?php

namespace App\Services;

use App\Enums\AdministrationVoucherStatus;
use App\Enums\NursingSupplySourceType;
use App\Enums\NursingVoucherStatus;
use App\Enums\UserRole;
use App\Models\AdministrationVoucher;
use App\Models\Cabinet;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\NursingVoucher;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DashboardService
{
    public function __construct(private readonly OperationalScheduleService $schedule) {}

    /** @return array<string, mixed> */
    public function for(User $user): array
    {
        $data = [
            'dashboardRole' => $user->role,
            'unreadNotificationCount' => $user->unreadNotifications()->count(),
        ];

        return match ($user->role) {
            UserRole::ADMINISTRATOR, UserRole::ROOT => [...$data, ...$this->administrator()],
            UserRole::WAREHOUSE_MANAGER => [...$data, ...$this->manager($user)],
            UserRole::NURSE => [...$data, ...$this->nurse($user, now())],
            default => $data,
        };
    }

    /** @return array<string, mixed> */
    private function administrator(): array
    {
        $inventory = InventoryItem::query();
        $nursing = NursingVoucher::query();
        $administration = AdministrationVoucher::query();

        return [
            'summary' => [
                'warehouses' => Warehouse::count(),
                'cabinets' => Cabinet::count(),
                'configured_products' => (clone $inventory)->distinct()->count('product_id'),
                ...$this->stockCounts(clone $inventory),
                ...$this->voucherCounts($nursing, $administration),
            ],
            'configurationPending' => [
                'warehouses_without_cabinet' => Warehouse::whereNull('default_nursing_cabinet_id')->count(),
                'nurses_without_warehouse' => User::where('role', UserRole::NURSE)->whereDoesntHave('warehouses')->count(),
                'nurses_without_hospital_link' => User::where('role', UserRole::NURSE)->whereNull('hospital_user_id')->count(),
            ],
            'recentNursingVouchers' => NursingVoucher::with(['requester:id,name,last_name_one', 'warehouse:id,name'])->latest('requested_at')->limit(5)->get(),
            'recentAdministrationVouchers' => AdministrationVoucher::with(['requester:id,name,last_name_one', 'warehouse:id,name', 'cabinet:id,name'])->latest('requested_at')->limit(5)->get(),
        ];
    }

    /** @return array<string, mixed> */
    private function manager(User $user): array
    {
        $warehouseIds = $user->warehouses()->pluck('warehouses.id');
        $inventory = $this->inventoryForWarehouses($warehouseIds);
        $nursing = NursingVoucher::whereIn('warehouse_id', $warehouseIds)->where('source_type', NursingSupplySourceType::WAREHOUSE);
        $administration = AdministrationVoucher::whereIn('warehouse_id', $warehouseIds);

        $nursingAttention = (clone $nursing)->with(['requester:id,name,last_name_one', 'warehouse:id,name'])
            ->whereIn('status', [NursingVoucherStatus::PENDING, NursingVoucherStatus::PARTIALLY_SUPPLIED])
            ->oldest('requested_at')->limit(10)->get()->map(fn ($voucher) => ['kind' => 'nursing', 'date' => $voucher->requested_at, 'voucher' => $voucher]);
        $administrationAttention = (clone $administration)->with(['requester:id,name,last_name_one', 'warehouse:id,name', 'cabinet:id,name'])
            ->whereIn('status', [AdministrationVoucherStatus::PENDING, AdministrationVoucherStatus::PARTIALLY_SUPPLIED])
            ->oldest('requested_at')->limit(10)->get()->map(fn ($voucher) => ['kind' => 'administration', 'date' => $voucher->requested_at, 'voucher' => $voucher]);

        return [
            'assignedWarehouses' => $user->warehouses()->orderBy('name')->get(['warehouses.id', 'warehouses.name']),
            'summary' => [...$this->stockCounts(clone $inventory), ...$this->voucherCounts($nursing, $administration)],
            'attentionVouchers' => $nursingAttention->concat($administrationAttention)->sortBy('date')->take(10)->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function nurse(User $user, CarbonInterface $at): array
    {
        $warehouse = $user->warehouseForNursing();
        $own = NursingVoucher::where('requested_by', $user->getKey());

        return [
            'nursingWarehouse' => $warehouse?->loadMissing('defaultNursingCabinet:id,name'),
            'warehouseAvailable' => $warehouse ? $this->schedule->isWarehouseAvailableAt($at) : null,
            'summary' => [
                'nursing_pending' => (clone $own)->where('status', NursingVoucherStatus::PENDING)->count(),
                'nursing_partial' => (clone $own)->where('status', NursingVoucherStatus::PARTIALLY_SUPPLIED)->count(),
                'nursing_recently_supplied' => (clone $own)->where('status', NursingVoucherStatus::SUPPLIED)->where('completed_at', '>=', now()->subDays(7))->count(),
            ],
            'recentNursingVouchers' => (clone $own)->with(['warehouse:id,name', 'sourceCabinet:id,name'])->latest('requested_at')->limit(5)->get(),
        ];
    }

    /** @return array<string, int> */
    private function stockCounts(Builder $inventory): array
    {
        return [
            'critical_stock' => $this->withStockCondition(clone $inventory, '<=')->count(),
            'low_stock' => $this->withStockCondition(clone $inventory, '>')->whereRaw($this->usableStockSql().' < inventory_items.maximum_stock', [today()->toDateString()])->count(),
            'expired_batches' => InventoryBatch::query()->whereIn('inventory_item_id', (clone $inventory)->select('inventory_items.id'))
                ->where('available_quantity', '>', 0)->whereDate('expiration_date', '<', today())->count(),
        ];
    }

    private function withStockCondition(Builder $query, string $operator): Builder
    {
        return $query->whereRaw($this->usableStockSql()." {$operator} inventory_items.minimum_stock", [today()->toDateString()]);
    }

    private function usableStockSql(): string
    {
        return 'COALESCE((SELECT SUM(inventory_batches.available_quantity) FROM inventory_batches WHERE inventory_batches.inventory_item_id = inventory_items.id AND inventory_batches.available_quantity > 0 AND (inventory_batches.expiration_date IS NULL OR DATE(inventory_batches.expiration_date) >= ?)), 0)';
    }

    private function inventoryForWarehouses(Collection $warehouseIds): Builder
    {
        return InventoryItem::query()->where(function (Builder $query) use ($warehouseIds): void {
            $query->where(fn (Builder $warehouse) => $warehouse->where('stockable_type', 'warehouse')->whereIn('stockable_id', $warehouseIds))
                ->orWhere(fn (Builder $cabinet) => $cabinet->where('stockable_type', 'cabinet')->whereIn('stockable_id', Cabinet::select('id')->whereIn('warehouse_id', $warehouseIds)));
        });
    }

    /** @return array<string, int> */
    private function voucherCounts(Builder $nursing, Builder $administration): array
    {
        return [
            'nursing_pending' => (clone $nursing)->where('status', NursingVoucherStatus::PENDING)->count(),
            'nursing_partial' => (clone $nursing)->where('status', NursingVoucherStatus::PARTIALLY_SUPPLIED)->count(),
            'administration_pending' => (clone $administration)->where('status', AdministrationVoucherStatus::PENDING)->count(),
            'administration_partial' => (clone $administration)->where('status', AdministrationVoucherStatus::PARTIALLY_SUPPLIED)->count(),
        ];
    }
}
