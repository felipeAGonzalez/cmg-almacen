<?php

namespace App\Http\Controllers;

use App\Enums\InventoryOutboundReason;
use App\Enums\ManualVoucherRequestingArea;
use App\Http\Requests\IndexInventoryOutboundRequest;
use App\Http\Requests\StoreInventoryOutboundRequest;
use App\Models\Cabinet;
use App\Models\InventoryItem;
use App\Models\InventoryOutbound;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\InventoryBatchAllocator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InventoryOutboundController extends Controller
{
    public function __construct(private readonly InventoryBatchAllocator $allocator) {}

    public function index(IndexInventoryOutboundRequest $request, Warehouse $warehouse): View
    {
        $filters = $request->validated();
        $query = $warehouse->inventoryOutbounds()
            ->with(['processedBy', 'sourceCabinet:id,name'])
            ->withCount('items');

        $query->when($filters['search'] ?? null, function ($query, string $search): void {
            $query->where(function ($query) use ($search): void {
                $query->where('patient_name', 'like', "%{$search}%")
                    ->orWhere('room_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('items.inventoryItem.product', fn ($productQuery) => $productQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('commercial_name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%"));
            });
        });

        $query->when($filters['reason'] ?? null, fn ($query, $reason) => $query->where('reason', $reason))
            ->when(($filters['source'] ?? null) === 'warehouse', fn ($query) => $query->whereNull('source_cabinet_id'))
            ->when(($filters['source'] ?? null) === 'cabinet', fn ($query) => $query->whereNotNull('source_cabinet_id'))
            ->when($filters['cabinet_id'] ?? null, fn ($query, $cabinetId) => $query->where('source_cabinet_id', $cabinetId))
            ->when($filters['requesting_area'] ?? null, fn ($query, $area) => $query->where('requesting_area', $area))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->where('processed_at', '>=', $date.' 00:00:00'))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->where('processed_at', '<=', $date.' 23:59:59'));

        $outbounds = $query
            ->orderByDesc('processed_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('outbounds.index', [
            'warehouse' => $warehouse,
            'outbounds' => $outbounds,
            'reasons' => InventoryOutboundReason::cases(),
            'requestingAreas' => ManualVoucherRequestingArea::cases(),
            'cabinets' => $warehouse->cabinets()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Warehouse $warehouse): View
    {
        $cabinets = $warehouse->cabinets()->orderBy('name')->get(['id', 'warehouse_id', 'name']);

        return view('outbounds.create', [
            'warehouse' => $warehouse,
            'cabinets' => $cabinets,
            'reasons' => InventoryOutboundReason::cases(),
            'requestingAreas' => ManualVoucherRequestingArea::cases(),
            'inventoryItems' => $this->sourceInventoryItems($warehouse, $cabinets->pluck('id')),
        ]);
    }

    public function store(StoreInventoryOutboundRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $data = $request->validated();

        $outbound = DB::transaction(function () use ($data, $request, $warehouse): InventoryOutbound {
            $source = isset($data['source_cabinet_id'])
                ? $warehouse->cabinets()->findOrFail($data['source_cabinet_id'])
                : $warehouse;
            $inventoryItems = $source->inventoryItems()
                ->whereIn('id', collect($data['items'])->pluck('inventory_item_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $outbound = $warehouse->inventoryOutbounds()->create([
                'source_cabinet_id' => $source instanceof Cabinet ? $source->id : null,
                'reason' => $data['reason'],
                'patient_name' => $data['patient_name'] ?? null,
                'room_number' => $data['room_number'] ?? null,
                'requesting_area' => $data['requesting_area'] ?? null,
                'notes' => $data['notes'] ?? null,
                'processed_by' => $request->user()->id,
                'processed_at' => isset($data['processed_at'])
                    ? Carbon::createFromFormat('Y-m-d\TH:i', $data['processed_at'], config('app.timezone'))
                    : now(),
                'source_type' => InventoryOutbound::SOURCE_MANUAL,
                'source_id' => null,
            ]);

            foreach ($data['items'] as $index => $dataItem) {
                $inventoryItem = $inventoryItems->get($dataItem['inventory_item_id']);
                $outboundItem = $outbound->items()->create([
                    'inventory_item_id' => $inventoryItem->id,
                    'requested_quantity' => $dataItem['quantity'],
                ]);
                $allocations = $this->allocator->allocate(
                    $inventoryItem,
                    (string) $dataItem['quantity'],
                    "items.$index.quantity",
                    'No hay existencia utilizable suficiente para retirar este producto.',
                );

                foreach ($allocations as $allocation) {
                    $batch = $allocation['batch'];
                    $quantity = $allocation['quantity'];
                    $batch->update([
                        'available_quantity' => bcsub($batch->available_quantity, $quantity, 3),
                    ]);
                    $outboundItem->allocations()->create([
                        'inventory_batch_id' => $batch->id,
                        'quantity' => $quantity,
                    ]);
                }
            }

            return $outbound;
        });

        return redirect()->route('warehouses.outbounds.show', [$warehouse, $outbound])
            ->with('success', 'Salida registrada correctamente.');
    }

    public function show(Warehouse $warehouse, InventoryOutbound $inventoryOutbound): View
    {
        $inventoryOutbound->load([
            'processedBy',
            'sourceCabinet:id,name,warehouse_id',
            'items.inventoryItem.product.unit',
            'items.allocations.inventoryBatch',
        ]);

        return view('outbounds.show', ['warehouse' => $warehouse, 'outbound' => $inventoryOutbound]);
    }

    /** @return Collection<int, InventoryItem> */
    private function sourceInventoryItems(Warehouse $warehouse, \Illuminate\Support\Collection $cabinetIds): Collection
    {
        return InventoryItem::query()
            ->where(function ($query) use ($warehouse, $cabinetIds): void {
                $query->where(fn ($warehouseInventory) => $warehouseInventory
                    ->where('stockable_type', $warehouse->getMorphClass())
                    ->where('stockable_id', $warehouse->id))
                    ->orWhere(fn ($cabinetInventory) => $cabinetInventory
                        ->where('stockable_type', (new Cabinet)->getMorphClass())
                        ->whereIn('stockable_id', $cabinetIds));
            })
            ->with('product.unit')
            ->withStockTotals()
            ->orderBy(Product::query()->select('name')->whereColumn('products.id', 'inventory_items.product_id'))
            ->get();
    }
}
