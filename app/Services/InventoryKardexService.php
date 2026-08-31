<?php

namespace App\Services;

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

    public function forWarehouse(Warehouse $warehouse, array $filters): LengthAwarePaginator
    {
        $query = $this->entryQuery($warehouse)
            ->unionAll($this->transferOutQuery($warehouse))
            ->unionAll($this->manualOutboundQuery($warehouse));

        return $this->paginate($query, $filters);
    }

    public function forCabinet(Warehouse $warehouse, Cabinet $cabinet, array $filters): LengthAwarePaginator
    {
        $query = $this->transferInQuery($warehouse, $cabinet);

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
                'inventory_outbound_allocations.id as sort_id',
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
        };
    }
}
