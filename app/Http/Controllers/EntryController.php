<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEntryRequest;
use App\Models\Entry;
use App\Models\Brand;
use App\Models\Category;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\Unit;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class EntryController extends Controller
{
    public function index(Warehouse $warehouse): View
    {
        $entries = $warehouse->entries()
            ->with([
                'supplier',
                'items:id,entry_id,quantity,unit_cost,vat_rate',
            ])
            ->withCount('items')
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->paginate(15);

        $manualReturns = $warehouse->inventoryOutboundReturns()
            ->with(['outbound', 'receivedBy'])
            ->withCount('items')
            ->orderByDesc('received_at')
            ->limit(10)
            ->get();

        return view('entries.index', compact('warehouse', 'entries', 'manualReturns'));
    }

    public function create(Warehouse $warehouse): View
    {
        return view('entries.create', [
            'warehouse' => $warehouse,
            'suppliers' => $warehouse->suppliers()->orderBy('name')->get(),
            'inventoryItems' => $this->inventoryItems($warehouse),
            'units' => Unit::query()->orderBy('name')->get(),
            'categories' => Category::query()->orderBy('name')->get(),
            'brands' => Brand::query()->orderBy('name')->get(),
            'locations' => $warehouse->locations()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreEntryRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $data = $request->validated();

        $entry = DB::transaction(function () use ($data, $warehouse): Entry {
            $entry = $warehouse->entries()->create(Arr::except($data, 'items'));

            $inventoryItems = InventoryItem::query()
                ->with('product.category')
                ->whereIn('id', collect($data['items'])->pluck('inventory_item_id'))
                ->get()
                ->keyBy('id');

            foreach ($data['items'] as $itemData) {
                $inventoryItem = $inventoryItems->get($itemData['inventory_item_id']);
                $entryItem = $entry->items()->create([
                    ...Arr::except($itemData, 'total_cost'),
                    'vat_rate' => $inventoryItem->product->isVatTaxable() ? '0.1600' : '0.0000',
                ]);
                $batch = $entryItem->batch()->create([
                    'inventory_item_id' => $entryItem->inventory_item_id,
                    'internal_lot' => 'PENDING-'.Str::uuid(),
                    'manufacturer_lot' => $entryItem->manufacturer_lot,
                    'expiration_date' => $entryItem->expiration_date,
                    'received_quantity' => $entryItem->quantity,
                    'available_quantity' => $entryItem->quantity,
                    'unit_cost' => $entryItem->unit_cost,
                ]);

                $batch->update(['internal_lot' => $this->internalLot($batch)]);
            }

            return $entry;
        });

        return redirect()->route('warehouses.entries.show', [$warehouse, $entry])
            ->with('success', 'Entrada registrada correctamente.');
    }

    public function show(Warehouse $warehouse, Entry $entry): View
    {
        $entry->load([
            'supplier',
            'items.inventoryItem.product.unit',
            'items.batch',
        ]);

        return view('entries.show', compact('warehouse', 'entry'));
    }

    /** @return Collection<int, InventoryItem> */
    private function inventoryItems(Warehouse $warehouse): Collection
    {
        return $warehouse->inventoryItems()
            ->with(['product.unit', 'product.category'])
            ->orderBy(Product::query()->select('name')->whereColumn('products.id', 'inventory_items.product_id'))
            ->get();
    }

    private function internalLot(InventoryBatch $batch): string
    {
        return sprintf('INT-%s-%06d', $batch->created_at->format('Ymd'), $batch->getKey());
    }
}
