<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryItemRequest;
use App\Http\Requests\UpdateInventoryItemRequest;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class WarehouseInventoryController extends Controller
{
    public function index(Warehouse $warehouse): View
    {
        $inventoryItems = $warehouse->inventoryItems()
            ->withStockTotals()
            ->with(['product.unit', 'location'])
            ->orderBy(Product::query()->select('name')->whereColumn('products.id', 'inventory_items.product_id'))
            ->paginate(15);

        return view('inventory.index', compact('warehouse', 'inventoryItems'));
    }

    public function create(Warehouse $warehouse): View
    {
        return view('inventory.create', [
            'warehouse' => $warehouse,
            ...$this->formData($warehouse),
        ]);
    }

    public function store(StoreInventoryItemRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $warehouse->inventoryItems()->create($request->validated());

        return redirect()->route('warehouses.inventory.index', $warehouse)
            ->with('success', 'Producto agregado al inventario correctamente.');
    }

    public function edit(Warehouse $warehouse, InventoryItem $inventoryItem): View
    {
        return view('inventory.edit', [
            'warehouse' => $warehouse,
            'inventoryItem' => $inventoryItem,
            ...$this->formData($warehouse),
        ]);
    }

    public function update(UpdateInventoryItemRequest $request, Warehouse $warehouse, InventoryItem $inventoryItem): RedirectResponse
    {
        $inventoryItem->update($request->validated());

        return redirect()->route('warehouses.inventory.index', $warehouse)
            ->with('success', 'Configuración de inventario actualizada correctamente.');
    }

    public function destroy(Warehouse $warehouse, InventoryItem $inventoryItem): RedirectResponse
    {
        if ($inventoryItem->adjustments()->exists()) {
            return redirect()->route('warehouses.inventory.index', $warehouse)
                ->with('error', 'No se puede retirar el producto porque tiene movimientos de inventario registrados.');
        }

        if ($inventoryItem->outboundItems()->exists()) {
            return redirect()->route('warehouses.inventory.index', $warehouse)
                ->with('error', 'No se puede retirar el producto porque tiene movimientos de inventario registrados.');
        }

        if ($inventoryItem->outgoingTransferItems()->exists() || $inventoryItem->incomingTransferItems()->exists()) {
            return redirect()->route('warehouses.inventory.index', $warehouse)
                ->with('error', 'No se puede retirar el producto porque tiene movimientos de inventario registrados.');
        }

        if ($inventoryItem->entryItems()->exists() || $inventoryItem->batches()->exists()) {
            return redirect()->route('warehouses.inventory.index', $warehouse)
                ->with('error', 'No se puede retirar el producto porque tiene existencias o entradas registradas.');
        }

        $inventoryItem->delete();

        return redirect()->route('warehouses.inventory.index', $warehouse)
            ->with('success', 'Producto retirado de la configuración del inventario.');
    }

    /** @return array{products: Collection, locations: Collection} */
    private function formData(Warehouse $warehouse): array
    {
        return [
            'products' => Product::query()->orderBy('name')->get(),
            'locations' => $warehouse->locations()->orderBy('name')->get(),
        ];
    }
}
