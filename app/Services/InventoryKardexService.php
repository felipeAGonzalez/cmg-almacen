<?php

namespace App\Services;

use App\Enums\InventoryAdjustmentReason;
use App\Enums\InventoryOutboundReason;
use App\Models\Cabinet;
use App\Models\InventoryItem;
use App\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InventoryKardexService
{
    public const ENTRY = 'entry';

    public const TRANSFER_IN = 'transfer_in';

    public const TRANSFER_OUT = 'transfer_out';

    public const MANUAL_OUTBOUND = 'manual_outbound';

    public const ADJUSTMENT_IN = 'adjustment_in';

    public const ADJUSTMENT_OUT = 'adjustment_out';

    public const NURSING_VOUCHER_WAREHOUSE_OUT = 'nursing_voucher_warehouse_out';

    public const NURSING_VOUCHER_CABINET_OUT = 'nursing_voucher_cabinet_out';

    public function forWarehouse(Warehouse $warehouse, array $filters): LengthAwarePaginator
    {
        $query = $this->entryQuery($warehouse)
            ->unionAll($this->transferOutQuery($warehouse))
            ->unionAll($this->manualOutboundQuery($warehouse))
            ->unionAll($this->adjustmentQuery($warehouse))
            ->unionAll($this->nursingVoucherOutQuery($warehouse, self::NURSING_VOUCHER_WAREHOUSE_OUT));

        return $this->paginate($query, $filters);
    }

    public function forCabinet(Warehouse $warehouse, Cabinet $cabinet, array $filters): LengthAwarePaginator
    {
        $query = $this->transferInQuery($warehouse, $cabinet)
            ->unionAll($this->adjustmentQuery($cabinet))
            ->unionAll($this->nursingVoucherOutQuery($cabinet, self::NURSING_VOUCHER_CABINET_OUT));

        return $this->paginate($query, $filters);
    }

    private function entryQuery(Warehouse $warehouse): Builder
    {
        return DB::table('entry_items')
            ->join('entries', 'entries.id', '=', 'entry_items.entry_id')
            ->join('suppliers', 'suppliers.id', '=', 'entries.supplier_id')
            ->join('inventory_items', 'inventory_items.id', '=', 'entry_items.inventory_item_id')
            ->join('products', 'products.id', '=', 'inventory_items.product_id')
            ->join('units', 'units.id', '=', 'products.unit_id')
            ->join('inventory_batches', 'inventory_batches.entry_item_id', '=', 'entry_items.id')
            ->where('entries.warehouse_id', $warehouse->id)
            ->select([
                'entries.created_at as occurred_at',
                DB::raw("'entry' as movement_type"),
                DB::raw("'in' as direction"),
                'entry_items.quantity',
                'entries.id as reference_id',
                'entries.invoice_number as reference_code',
                'inventory_items.id as inventory_item_id',
                'products.name as product_name',
                'units.name as unit_name',
                'inventory_batches.internal_lot',
                DB::raw('null as source_internal_lot'),
                'inventory_batches.manufacturer_lot',
                'inventory_batches.expiration_date',
                'suppliers.name as source_name',
                DB::raw('null as destination_name'),
                DB::raw('null as actor_name'),
                DB::raw('null as actor_last_name'),
                DB::raw('null as detail'),
                DB::raw('null as patient_name'),
                DB::raw('null as room_number'),
                'inventory_batches.id as sort_id',
            ]);
    }

    private function transferOutQuery(Warehouse $warehouse): Builder
    {
        return DB::table('inventory_transfer_allocations')
            ->join('inventory_transfer_items', 'inventory_transfer_items.id', '=', 'inventory_transfer_allocations.inventory_transfer_item_id')
            ->join('inventory_transfers', 'inventory_transfers.id', '=', 'inventory_transfer_items.inventory_transfer_id')
            ->join('cabinets', 'cabinets.id', '=', 'inventory_transfers.cabinet_id')
            ->join('users', 'users.id', '=', 'inventory_transfers.transferred_by')
            ->join('inventory_items', 'inventory_items.id', '=', 'inventory_transfer_items.source_inventory_item_id')
            ->join('products', 'products.id', '=', 'inventory_items.product_id')
            ->join('units', 'units.id', '=', 'products.unit_id')
            ->join('inventory_batches', 'inventory_batches.id', '=', 'inventory_transfer_allocations.source_batch_id')
            ->where('inventory_transfers.warehouse_id', $warehouse->id)
            ->select([
                'inventory_transfers.transferred_at as occurred_at',
                DB::raw("'transfer_out' as movement_type"),
                DB::raw("'out' as direction"),
                'inventory_transfer_allocations.quantity',
                'inventory_transfers.id as reference_id',
                DB::raw('null as reference_code'),
                'inventory_items.id as inventory_item_id',
                'products.name as product_name',
                'units.name as unit_name',
                'inventory_batches.internal_lot',
                DB::raw('null as source_internal_lot'),
                'inventory_batches.manufacturer_lot',
                'inventory_batches.expiration_date',
                DB::raw('null as source_name'),
                'cabinets.name as destination_name',
                'users.name as actor_name',
                'users.last_name_one as actor_last_name',
                DB::raw('null as detail'),
                DB::raw('null as patient_name'),
                DB::raw('null as room_number'),
                'inventory_transfer_allocations.id as sort_id',
            ]);
    }

    private function transferInQuery(Warehouse $warehouse, Cabinet $cabinet): Builder
    {
        return DB::table('inventory_transfer_allocations')
            ->join('inventory_transfer_items', 'inventory_transfer_items.id', '=', 'inventory_transfer_allocations.inventory_transfer_item_id')
            ->join('inventory_transfers', 'inventory_transfers.id', '=', 'inventory_transfer_items.inventory_transfer_id')
            ->join('warehouses', 'warehouses.id', '=', 'inventory_transfers.warehouse_id')
            ->join('users', 'users.id', '=', 'inventory_transfers.transferred_by')
            ->join('inventory_items', 'inventory_items.id', '=', 'inventory_transfer_items.destination_inventory_item_id')
            ->join('products', 'products.id', '=', 'inventory_items.product_id')
            ->join('units', 'units.id', '=', 'products.unit_id')
            ->join('inventory_batches', 'inventory_batches.id', '=', 'inventory_transfer_allocations.destination_batch_id')
            ->join('inventory_batches as source_batches', 'source_batches.id', '=', 'inventory_transfer_allocations.source_batch_id')
            ->where('inventory_transfers.warehouse_id', $warehouse->id)
            ->where('inventory_transfers.cabinet_id', $cabinet->id)
            ->select([
                'inventory_transfers.transferred_at as occurred_at',
                DB::raw("'transfer_in' as movement_type"),
                DB::raw("'in' as direction"),
                'inventory_transfer_allocations.quantity',
                'inventory_transfers.id as reference_id',
                DB::raw('null as reference_code'),
                'inventory_items.id as inventory_item_id',
                'products.name as product_name',
                'units.name as unit_name',
                'inventory_batches.internal_lot',
                'source_batches.internal_lot as source_internal_lot',
                'inventory_batches.manufacturer_lot',
                'inventory_batches.expiration_date',
                'warehouses.name as source_name',
                DB::raw('null as destination_name'),
                'users.name as actor_name',
                'users.last_name_one as actor_last_name',
                DB::raw('null as detail'),
                DB::raw('null as patient_name'),
                DB::raw('null as room_number'),
                'inventory_transfer_allocations.id as sort_id',
            ]);
    }

    private function manualOutboundQuery(Warehouse $warehouse): Builder
    {
        return DB::table('inventory_outbound_allocations')
            ->join('inventory_outbound_items', 'inventory_outbound_items.id', '=', 'inventory_outbound_allocations.inventory_outbound_item_id')
            ->join('inventory_outbounds', 'inventory_outbounds.id', '=', 'inventory_outbound_items.inventory_outbound_id')
            ->join('users', 'users.id', '=', 'inventory_outbounds.processed_by')
            ->join('inventory_items', 'inventory_items.id', '=', 'inventory_outbound_items.inventory_item_id')
            ->join('products', 'products.id', '=', 'inventory_items.product_id')
            ->join('units', 'units.id', '=', 'products.unit_id')
            ->join('inventory_batches', 'inventory_batches.id', '=', 'inventory_outbound_allocations.inventory_batch_id')
            ->where('inventory_outbounds.warehouse_id', $warehouse->id)
            ->select([
                'inventory_outbounds.processed_at as occurred_at',
                DB::raw("'manual_outbound' as movement_type"),
                DB::raw("'out' as direction"),
                'inventory_outbound_allocations.quantity',
                'inventory_outbounds.id as reference_id',
                DB::raw('null as reference_code'),
                'inventory_items.id as inventory_item_id',
                'products.name as product_name',
                'units.name as unit_name',
                'inventory_batches.internal_lot',
                DB::raw('null as source_internal_lot'),
                'inventory_batches.manufacturer_lot',
                'inventory_batches.expiration_date',
                DB::raw('null as source_name'),
                DB::raw('null as destination_name'),
                'users.name as actor_name',
                'users.last_name_one as actor_last_name',
                'inventory_outbounds.reason as detail',
                DB::raw('null as patient_name'),
                DB::raw('null as room_number'),
                'inventory_outbound_allocations.id as sort_id',
            ]);
    }

    private function nursingVoucherOutQuery(Warehouse|Cabinet $stockable, string $movementType): Builder
    {
        $sourceType = $stockable instanceof Warehouse ? 'warehouse' : 'cabinet';

        return DB::table('nursing_voucher_allocations')
            ->join('nursing_voucher_fulfillment_items', 'nursing_voucher_fulfillment_items.id', '=', 'nursing_voucher_allocations.nursing_voucher_fulfillment_item_id')
            ->join('nursing_voucher_fulfillments', 'nursing_voucher_fulfillments.id', '=', 'nursing_voucher_fulfillment_items.nursing_voucher_fulfillment_id')
            ->join('nursing_voucher_items', 'nursing_voucher_items.id', '=', 'nursing_voucher_fulfillment_items.nursing_voucher_item_id')
            ->join('nursing_vouchers', 'nursing_vouchers.id', '=', 'nursing_voucher_fulfillments.nursing_voucher_id')
            ->join('users', 'users.id', '=', 'nursing_voucher_fulfillments.supplied_by')
            ->join('inventory_batches', 'inventory_batches.id', '=', 'nursing_voucher_allocations.inventory_batch_id')
            ->join('inventory_items', 'inventory_items.id', '=', 'inventory_batches.inventory_item_id')
            ->join('products', 'products.id', '=', 'inventory_items.product_id')
            ->join('units', 'units.id', '=', 'products.unit_id')
            ->leftJoin('inventory_batches as source_batches', 'source_batches.id', '=', 'inventory_batches.source_batch_id')
            ->where('nursing_vouchers.source_type', $sourceType)
            ->where('nursing_vouchers.warehouse_id', $stockable instanceof Warehouse ? $stockable->id : $stockable->warehouse_id)
            ->where('inventory_items.stockable_type', $stockable->getMorphClass())
            ->where('inventory_items.stockable_id', $stockable->id)
            ->whereColumn('nursing_voucher_items.nursing_voucher_id', 'nursing_vouchers.id')
            ->whereColumn('nursing_voucher_items.product_id', 'inventory_items.product_id')
            ->when($stockable instanceof Cabinet, fn (Builder $query) => $query->where('nursing_vouchers.source_cabinet_id', $stockable->id))
            ->select([
                'nursing_voucher_fulfillments.supplied_at as occurred_at',
                DB::raw("'".$movementType."' as movement_type"),
                DB::raw("'out' as direction"),
                'nursing_voucher_allocations.quantity',
                'nursing_vouchers.id as reference_id',
                DB::raw('null as reference_code'),
                'inventory_items.id as inventory_item_id',
                'products.name as product_name',
                'units.name as unit_name',
                'inventory_batches.internal_lot',
                'source_batches.internal_lot as source_internal_lot',
                'inventory_batches.manufacturer_lot',
                'inventory_batches.expiration_date',
                DB::raw('null as source_name'),
                DB::raw('null as destination_name'),
                'users.name as actor_name',
                'users.last_name_one as actor_last_name',
                DB::raw('null as detail'),
                'nursing_vouchers.patient_name',
                'nursing_vouchers.room_number',
                'nursing_voucher_allocations.id as sort_id',
            ]);
    }

    private function adjustmentQuery(Warehouse|Cabinet $stockable): Builder
    {
        return DB::table('inventory_adjustments')
            ->join('users', 'users.id', '=', 'inventory_adjustments.adjusted_by')
            ->join('inventory_items', 'inventory_items.id', '=', 'inventory_adjustments.inventory_item_id')
            ->join('products', 'products.id', '=', 'inventory_items.product_id')
            ->join('units', 'units.id', '=', 'products.unit_id')
            ->join('inventory_batches', 'inventory_batches.id', '=', 'inventory_adjustments.inventory_batch_id')
            ->leftJoin('inventory_batches as source_batches', 'source_batches.id', '=', 'inventory_batches.source_batch_id')
            ->where('inventory_items.stockable_type', $stockable->getMorphClass())
            ->where('inventory_items.stockable_id', $stockable->id)
            ->select([
                'inventory_adjustments.adjusted_at as occurred_at',
                DB::raw("case when inventory_adjustments.difference > 0 then 'adjustment_in' else 'adjustment_out' end as movement_type"),
                DB::raw("case when inventory_adjustments.difference > 0 then 'in' else 'out' end as direction"),
                DB::raw('abs(inventory_adjustments.difference) as quantity'),
                'inventory_adjustments.id as reference_id',
                DB::raw('null as reference_code'),
                'inventory_items.id as inventory_item_id',
                'products.name as product_name',
                'units.name as unit_name',
                'inventory_batches.internal_lot',
                'source_batches.internal_lot as source_internal_lot',
                'inventory_batches.manufacturer_lot',
                'inventory_batches.expiration_date',
                DB::raw('null as source_name'),
                DB::raw('null as destination_name'),
                'users.name as actor_name',
                'users.last_name_one as actor_last_name',
                'inventory_adjustments.reason as detail',
                DB::raw('null as patient_name'),
                DB::raw('null as room_number'),
                'inventory_adjustments.id as sort_id',
            ]);
    }

    private function paginate(Builder $union, array $filters): LengthAwarePaginator
    {
        $query = DB::query()->fromSub($union, 'kardex_movements');

        $query->when($filters['inventory_item_id'] ?? null, fn (Builder $builder, $id) => $builder->where('inventory_item_id', $id))
            ->when($filters['movement_type'] ?? null, fn (Builder $builder, $type) => $builder->where('movement_type', $type))
            ->when($filters['date_from'] ?? null, fn (Builder $builder, $date) => $builder->whereDate('occurred_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $builder, $date) => $builder->whereDate('occurred_at', '<=', $date))
            ->orderByDesc('occurred_at')
            ->orderByDesc('sort_id')
            ->orderByDesc('movement_type');

        $paginator = $query->paginate(25)->withQueryString();
        $paginator->setCollection($paginator->getCollection()->map(function (object $movement): object {
            $movement->occurred_at = Carbon::parse($movement->occurred_at);
            $movement->expiration_date = $movement->expiration_date ? Carbon::parse($movement->expiration_date) : null;
            $movement->movement_label = $this->movementLabel($movement->movement_type);
            $movement->reason_label = match ($movement->movement_type) {
                self::MANUAL_OUTBOUND => InventoryOutboundReason::tryFrom($movement->detail ?? '')?->label(),
                self::ADJUSTMENT_IN, self::ADJUSTMENT_OUT => InventoryAdjustmentReason::tryFrom($movement->detail ?? '')?->label(),
                default => null,
            };
            $movement->formatted_quantity = InventoryItem::formatQuantity($movement->quantity);

            return $movement;
        }));

        return $paginator;
    }

    private function movementLabel(string $type): string
    {
        return match ($type) {
            self::ENTRY => 'Entrada',
            self::TRANSFER_IN => 'Transferencia recibida',
            self::TRANSFER_OUT => 'Transferencia enviada',
            self::MANUAL_OUTBOUND => 'Salida manual',
            self::ADJUSTMENT_IN => 'Ajuste positivo',
            self::ADJUSTMENT_OUT => 'Ajuste negativo',
            self::NURSING_VOUCHER_WAREHOUSE_OUT => 'Vale de Enfermería — Salida de almacén',
            self::NURSING_VOUCHER_CABINET_OUT => 'Vale de Enfermería — Salida de gabinete',
        };
    }
}
