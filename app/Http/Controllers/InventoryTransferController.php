<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryTransferRequest;
use App\Models\InventoryItem;
use App\Models\InventoryTransfer;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\InventoryTransferService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class InventoryTransferController extends Controller
{
    public function __construct(private readonly InventoryTransferService $transfers) {}

    public function index(Warehouse $warehouse): View
    {
        $transfers = $warehouse->inventoryTransfers()->with(['cabinet', 'transferredBy'])->withCount('items')
            ->orderByDesc('transferred_at')->orderByDesc('id')->paginate(15);

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
        $cabinet = $warehouse->cabinets()->findOrFail($data['cabinet_id']);
        $transfer = $this->transfers->transfer($warehouse, $cabinet, $request->user(), $data['items'], $data['notes']);

        return redirect()->route('warehouses.transfers.show', [$warehouse, $transfer])->with('success', 'Transferencia registrada correctamente.');
    }

    public function show(Warehouse $warehouse, InventoryTransfer $inventoryTransfer): View
    {
        $inventoryTransfer->load([
            'cabinet', 'transferredBy', 'administrationVoucher',
            'items.sourceInventoryItem.product.unit', 'items.destinationInventoryItem.product.unit',
            'items.allocations.sourceBatch', 'items.allocations.destinationBatch',
        ]);

        return view('transfers.show', ['warehouse' => $warehouse, 'transfer' => $inventoryTransfer]);
    }

    /** @return Collection<int, InventoryItem> */
    private function sourceInventoryItems(Warehouse $warehouse): Collection
    {
        return $warehouse->inventoryItems()->with('product.unit')->withStockTotals()
            ->orderBy(Product::query()->select('name')->whereColumn('products.id', 'inventory_items.product_id'))->get();
    }
}
