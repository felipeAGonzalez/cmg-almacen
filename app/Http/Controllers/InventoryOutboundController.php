<?php

namespace App\Http\Controllers;

use App\Enums\InventoryOutboundReason;
use App\Http\Requests\StoreInventoryOutboundRequest;
use App\Models\InventoryItem;
use App\Models\InventoryOutbound;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\InventoryBatchAllocator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InventoryOutboundController extends Controller
{
    public function __construct(private readonly InventoryBatchAllocator $allocator) {}

    public function index(Warehouse $warehouse): View
    {
        $outbounds = $warehouse->inventoryOutbounds()
            ->with('processedBy')
            ->withCount('items')
            ->orderByDesc('processed_at')
            ->orderByDesc('id')
            ->paginate(15);

        return view('outbounds.index', compact('warehouse', 'outbounds'));
    }

    public function create(Warehouse $warehouse): View
    {
        return view('outbounds.create', [
            'warehouse' => $warehouse,
            'reasons' => InventoryOutboundReason::cases(),
            'inventoryItems' => $this->sourceInventoryItems($warehouse),
        ]);
    }

    public function store(StoreInventoryOutboundRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $data = $request->validated();

        $outbound = DB::transaction(function () use ($data, $request, $warehouse): InventoryOutbound {
            $inventoryItems = $warehouse->inventoryItems()
                ->whereIn('id', collect($data['items'])->pluck('inventory_item_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $outbound = $warehouse->inventoryOutbounds()->create([
                'reason' => $data['reason'],
                'notes' => $data['notes'],
                'processed_by' => $request->user()->id,
                'processed_at' => now(),
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
            'items.inventoryItem.product.unit',
            'items.allocations.inventoryBatch',
        ]);

        return view('outbounds.show', ['warehouse' => $warehouse, 'outbound' => $inventoryOutbound]);
    }

    /** @return Collection<int, InventoryItem> */
    private function sourceInventoryItems(Warehouse $warehouse): Collection
    {
        return $warehouse->inventoryItems()
            ->with('product.unit')
            ->withStockTotals()
            ->orderBy(Product::query()->select('name')->whereColumn('products.id', 'inventory_items.product_id'))
            ->get();
    }
}
