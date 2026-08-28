<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryTransferRequest;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryTransfer;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\InventoryBatchAllocator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InventoryTransferController extends Controller
{
    public function __construct(private readonly InventoryBatchAllocator $allocator) {}

    public function index(Warehouse $warehouse): View
    {
        $transfers = $warehouse->inventoryTransfers()
            ->with(['cabinet', 'transferredBy'])
            ->withCount('items')
            ->orderByDesc('transferred_at')
            ->orderByDesc('id')
            ->paginate(15);

        return view('transfers.index', compact('warehouse', 'transfers'));
    }

    public function create(Warehouse $warehouse): View
    {
        return view('transfers.create', [
            'warehouse' => $warehouse,
            'cabinets' => $warehouse->cabinets()->with('inventoryItems:id,stockable_type,stockable_id,product_id')->orderBy('name')->get(),
            'inventoryItems' => $this->sourceInventoryItems($warehouse),
        ]);
    }

    public function store(StoreInventoryTransferRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $data = $request->validated();

        $transfer = DB::transaction(function () use ($data, $request, $warehouse): InventoryTransfer {
            $cabinet = $warehouse->cabinets()->lockForUpdate()->findOrFail($data['cabinet_id']);
            $sourceItems = $warehouse->inventoryItems()
                ->whereIn('id', collect($data['items'])->pluck('source_inventory_item_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $resolvedItems = collect($data['items'])->map(function (array $item, int $index) use ($cabinet, $sourceItems): array {
                $source = $sourceItems->get($item['source_inventory_item_id']);
                $destination = $cabinet->inventoryItems()
                    ->where('product_id', $source->product_id)
                    ->lockForUpdate()
                    ->first();

                if (! $destination) {
                    throw ValidationException::withMessages([
                        "items.$index.source_inventory_item_id" => 'El producto no está configurado en el inventario del gabinete.',
                    ]);
                }

                return [...$item, 'source' => $source, 'destination' => $destination];
            });

            $transfer = $warehouse->inventoryTransfers()->create([
                'cabinet_id' => $cabinet->id,
                'transferred_by' => $request->user()->id,
                'transferred_at' => now(),
                'notes' => $data['notes'],
            ]);

            foreach ($resolvedItems as $index => $resolved) {
                $transferItem = $transfer->items()->create([
                    'source_inventory_item_id' => $resolved['source']->id,
                    'destination_inventory_item_id' => $resolved['destination']->id,
                    'requested_quantity' => $resolved['quantity'],
                ]);

                $allocations = $this->allocator->allocate(
                    $resolved['source'],
                    (string) $resolved['quantity'],
                    "items.$index.quantity",
                );

                foreach ($allocations as $allocation) {
                    $sourceBatch = $allocation['batch'];
                    $quantity = $allocation['quantity'];
                    $sourceBatch->update([
                        'available_quantity' => bcsub($sourceBatch->available_quantity, $quantity, 3),
                    ]);
                    $destinationBatch = $this->destinationBatch($resolved['destination'], $sourceBatch, $quantity);
                    $transferItem->allocations()->create([
                        'source_batch_id' => $sourceBatch->id,
                        'destination_batch_id' => $destinationBatch->id,
                        'quantity' => $quantity,
                    ]);
                }
            }

            return $transfer;
        });

        return redirect()->route('warehouses.transfers.show', [$warehouse, $transfer])
            ->with('success', 'Transferencia registrada correctamente.');
    }

    public function show(Warehouse $warehouse, InventoryTransfer $inventoryTransfer): View
    {
        $inventoryTransfer->load([
            'cabinet',
            'transferredBy',
            'items.sourceInventoryItem.product.unit',
            'items.destinationInventoryItem.product.unit',
            'items.allocations.sourceBatch',
            'items.allocations.destinationBatch',
        ]);

        return view('transfers.show', ['warehouse' => $warehouse, 'transfer' => $inventoryTransfer]);
    }

    private function destinationBatch(InventoryItem $destination, InventoryBatch $source, string $quantity): InventoryBatch
    {
        $batch = $destination->batches()->where('source_batch_id', $source->id)->lockForUpdate()->first();

        if ($batch) {
            $batch->update([
                'received_quantity' => bcadd($batch->received_quantity, $quantity, 3),
                'available_quantity' => bcadd($batch->available_quantity, $quantity, 3),
            ]);

            return $batch;
        }

        $batch = $destination->batches()->create([
            'entry_item_id' => null,
            'source_batch_id' => $source->id,
            'internal_lot' => 'PENDING-'.Str::uuid(),
            'manufacturer_lot' => $source->manufacturer_lot,
            'expiration_date' => $source->expiration_date,
            'received_quantity' => $quantity,
            'available_quantity' => $quantity,
            'unit_cost' => $source->unit_cost,
        ]);
        $batch->update(['internal_lot' => sprintf('INT-%s-%06d', $batch->created_at->format('Ymd'), $batch->id)]);

        return $batch;
    }

    /** @return Collection<int, InventoryItem> */
    private function sourceInventoryItems(Warehouse $warehouse): Collection
    {
        return $warehouse->inventoryItems()
            ->with('product.unit')
            ->withSum(['batches as usable_stock' => fn ($query) => $query
                ->where('available_quantity', '>', 0)
                ->where(fn ($batchQuery) => $batchQuery->whereNull('expiration_date')->orWhereDate('expiration_date', '>=', today()))], 'available_quantity')
            ->orderBy(Product::query()->select('name')->whereColumn('products.id', 'inventory_items.product_id'))
            ->get();
    }
}
