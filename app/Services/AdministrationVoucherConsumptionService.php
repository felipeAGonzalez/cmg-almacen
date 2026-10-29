<?php

namespace App\Services;

use App\Enums\NursingSupplySourceType;
use App\Enums\NursingVoucherReturnStatus;
use App\Models\AdministrationVoucher;
use App\Models\AdministrationVoucherConsumption;
use App\Models\Cabinet;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AdministrationVoucherConsumptionService
{
    public function __construct(private readonly AdministrationVoucherService $vouchers) {}

    /** @return Collection<int, array<string, mixed>> */
    public function pendingGroups(User $user): Collection
    {
        Gate::forUser($user)->authorize('create', AdministrationVoucher::class);

        $warehouseIds = $user->isAdmin()
            ? null
            : $user->warehouses()->pluck('warehouses.id')->map(fn ($id): int => (int) $id)->all();

        $rows = $this->pendingRows(warehouseIds: $warehouseIds);

        if ($rows->isEmpty()) {
            return collect();
        }

        $configuredProducts = DB::table('inventory_items')
            ->where('stockable_type', (new Warehouse)->getMorphClass())
            ->whereIn('stockable_id', $rows->pluck('warehouse_id')->unique())
            ->whereIn('product_id', $rows->pluck('product_id')->unique())
            ->get(['stockable_id', 'product_id'])
            ->mapWithKeys(fn (object $item): array => [
                $item->stockable_id.'|'.$item->product_id => true,
            ]);

        return $rows
            ->groupBy(fn (object $row): string => $row->cabinet_id.'|'.$row->consumption_date)
            ->map(function (Collection $rows) use ($configuredProducts): array {
                $first = $rows->first();
                $products = $rows->groupBy('product_id')->map(function (Collection $productRows): array {
                    $firstProduct = $productRows->first();

                    return [
                        'product_id' => (int) $firstProduct->product_id,
                        'name' => $firstProduct->product_name,
                        'quantity' => $this->sumQuantities($productRows->pluck('quantity')),
                    ];
                })->values();

                return [
                    'warehouse_id' => (int) $first->warehouse_id,
                    'warehouse_name' => $first->warehouse_name,
                    'cabinet_id' => (int) $first->cabinet_id,
                    'cabinet_name' => $first->cabinet_name,
                    'consumption_date' => $first->consumption_date,
                    'products' => $products,
                    'total_products' => $products->count(),
                    'can_generate' => $products->every(fn (array $product): bool => $configuredProducts->has(
                        $first->warehouse_id.'|'.$product['product_id'],
                    )),
                ];
            })
            ->sortByDesc('consumption_date')
            ->values();
    }

    public function generate(User $user, Cabinet $cabinet, string $consumptionDate): AdministrationVoucher
    {
        Gate::forUser($user)->authorize('create', AdministrationVoucher::class);
        $cabinet->loadMissing('warehouse');

        if (! $user->isAdmin() && ! $user->canManageWarehouse($cabinet->warehouse)) {
            throw new AuthorizationException('No puedes generar vales para este almacén.');
        }

        $date = CarbonImmutable::parse($consumptionDate, config('app.timezone'))->startOfDay();
        if (! $date->isBefore(today())) {
            throw ValidationException::withMessages([
                'consumption_date' => 'Sólo se pueden generar vales de consumos de días anteriores.',
            ]);
        }

        return DB::transaction(function () use ($user, $cabinet, $date): AdministrationVoucher {
            $rows = $this->pendingRows($cabinet->id, $date->toDateString());
            if ($rows->isEmpty()) {
                throw ValidationException::withMessages([
                    'consumption' => 'Ya no existen consumos pendientes de reposición para este gabinete y fecha.',
                ]);
            }

            $items = $rows->groupBy('product_id')->map(fn (Collection $productRows): array => [
                'product_id' => (int) $productRows->first()->product_id,
                'quantity' => $this->sumQuantities($productRows->pluck('quantity')),
            ])->values()->all();

            $voucher = $this->vouchers->create(
                $user,
                $cabinet->warehouse,
                $cabinet,
                $items,
                'Generado automáticamente por el consumo del gabinete del '.$date->format('d/m/Y').'.',
            );

            foreach ($rows as $row) {
                $voucher->consumptions()->create([
                    'source_type' => $row->source_type,
                    'source_id' => $row->source_id,
                    'product_id' => $row->product_id,
                    'quantity' => $row->quantity,
                    'consumption_date' => $row->consumption_date,
                ]);
            }

            return $voucher->load('consumptions');
        });
    }

    /** @return Collection<int, object> */
    private function pendingRows(?int $cabinetId = null, ?string $date = null, ?array $warehouseIds = null): Collection
    {
        return $this->nursingRows($cabinetId, $date, $warehouseIds)
            ->concat($this->manualRows($cabinetId, $date, $warehouseIds));
    }

    /** @return Collection<int, object> */
    private function nursingRows(?int $cabinetId, ?string $date, ?array $warehouseIds): Collection
    {
        $returned = DB::table('nursing_voucher_return_items as nvri')
            ->join('nursing_voucher_returns as nvr', 'nvr.id', '=', 'nvri.nursing_voucher_return_id')
            ->where('nvr.status', NursingVoucherReturnStatus::RECEIVED->value)
            ->groupBy('nvri.nursing_voucher_allocation_id')
            ->selectRaw('nvri.nursing_voucher_allocation_id as allocation_id, SUM(nvri.quantity) as returned_quantity');

        return DB::table('nursing_voucher_allocations as nva')
            ->join('nursing_voucher_fulfillment_items as nvfi', 'nvfi.id', '=', 'nva.nursing_voucher_fulfillment_item_id')
            ->join('nursing_voucher_fulfillments as nvf', 'nvf.id', '=', 'nvfi.nursing_voucher_fulfillment_id')
            ->join('nursing_vouchers as nv', 'nv.id', '=', 'nvf.nursing_voucher_id')
            ->join('inventory_batches as ib', 'ib.id', '=', 'nva.inventory_batch_id')
            ->join('inventory_items as ii', 'ii.id', '=', 'ib.inventory_item_id')
            ->join('products as p', 'p.id', '=', 'ii.product_id')
            ->join('cabinets as c', 'c.id', '=', 'nv.source_cabinet_id')
            ->join('warehouses as w', 'w.id', '=', 'c.warehouse_id')
            ->leftJoinSub($returned, 'returned', 'returned.allocation_id', '=', 'nva.id')
            ->where('nv.source_type', NursingSupplySourceType::CABINET->value)
            ->whereColumn('ii.stockable_id', 'nv.source_cabinet_id')
            ->where('ii.stockable_type', (new Cabinet)->getMorphClass())
            ->where('nvf.supplied_at', '<', today()->startOfDay())
            ->when($cabinetId, fn (Builder $query, int $value): Builder => $query->where('nv.source_cabinet_id', $value))
            ->when($date, fn (Builder $query, string $value): Builder => $query->whereDate('nvf.supplied_at', $value))
            ->when($warehouseIds !== null, fn (Builder $query): Builder => $query->whereIn('w.id', $warehouseIds))
            ->whereRaw('(nva.quantity - COALESCE(returned.returned_quantity, 0)) > 0')
            ->whereNotExists(fn (Builder $query): Builder => $query
                ->selectRaw('1')
                ->from('administration_voucher_consumptions as avc')
                ->where('avc.source_type', AdministrationVoucherConsumption::NURSING_ALLOCATION)
                ->whereColumn('avc.source_id', 'nva.id'))
            ->selectRaw("'".AdministrationVoucherConsumption::NURSING_ALLOCATION."' as source_type, nva.id as source_id, ii.product_id, p.name as product_name, (nva.quantity - COALESCE(returned.returned_quantity, 0)) as quantity, DATE(nvf.supplied_at) as consumption_date, c.id as cabinet_id, c.name as cabinet_name, w.id as warehouse_id, w.name as warehouse_name")
            ->get();
    }

    /** @return Collection<int, object> */
    private function manualRows(?int $cabinetId, ?string $date, ?array $warehouseIds): Collection
    {
        $returned = DB::table('inventory_outbound_return_items as iori')
            ->groupBy('iori.inventory_outbound_allocation_id')
            ->selectRaw('iori.inventory_outbound_allocation_id as allocation_id, SUM(iori.quantity) as returned_quantity');

        return DB::table('inventory_outbound_allocations as ioa')
            ->join('inventory_outbound_items as ioi', 'ioi.id', '=', 'ioa.inventory_outbound_item_id')
            ->join('inventory_outbounds as io', 'io.id', '=', 'ioi.inventory_outbound_id')
            ->join('inventory_batches as ib', 'ib.id', '=', 'ioa.inventory_batch_id')
            ->join('inventory_items as ii', 'ii.id', '=', 'ib.inventory_item_id')
            ->join('products as p', 'p.id', '=', 'ii.product_id')
            ->join('cabinets as c', 'c.id', '=', 'io.source_cabinet_id')
            ->join('warehouses as w', 'w.id', '=', 'c.warehouse_id')
            ->leftJoinSub($returned, 'returned', 'returned.allocation_id', '=', 'ioa.id')
            ->whereNotNull('io.source_cabinet_id')
            ->whereColumn('ii.stockable_id', 'io.source_cabinet_id')
            ->where('ii.stockable_type', (new Cabinet)->getMorphClass())
            ->where('io.processed_at', '<', today()->startOfDay())
            ->when($cabinetId, fn (Builder $query, int $value): Builder => $query->where('io.source_cabinet_id', $value))
            ->when($date, fn (Builder $query, string $value): Builder => $query->whereDate('io.processed_at', $value))
            ->when($warehouseIds !== null, fn (Builder $query): Builder => $query->whereIn('w.id', $warehouseIds))
            ->whereRaw('(ioa.quantity - COALESCE(returned.returned_quantity, 0)) > 0')
            ->whereNotExists(fn (Builder $query): Builder => $query
                ->selectRaw('1')
                ->from('administration_voucher_consumptions as avc')
                ->where('avc.source_type', AdministrationVoucherConsumption::MANUAL_OUTBOUND_ALLOCATION)
                ->whereColumn('avc.source_id', 'ioa.id'))
            ->selectRaw("'".AdministrationVoucherConsumption::MANUAL_OUTBOUND_ALLOCATION."' as source_type, ioa.id as source_id, ii.product_id, p.name as product_name, (ioa.quantity - COALESCE(returned.returned_quantity, 0)) as quantity, DATE(io.processed_at) as consumption_date, c.id as cabinet_id, c.name as cabinet_name, w.id as warehouse_id, w.name as warehouse_name")
            ->get();
    }

    private function sumQuantities(Collection $quantities): string
    {
        return $quantities->reduce(
            fn (string $total, mixed $quantity): string => bcadd($total, (string) $quantity, 3),
            '0.000',
        );
    }
}
