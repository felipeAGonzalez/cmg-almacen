<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryItemRequest;
use App\Http\Requests\UpdateInventoryItemRequest;
use App\Models\Cabinet;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CabinetInventoryController extends Controller
{
    public function index(Warehouse $warehouse, Cabinet $cabinet): View
    {
        $inventoryItems = $cabinet->inventoryItems()
            ->withStockTotals()
            ->with('product.unit')
            ->orderBy(Product::query()->select('name')->whereColumn('products.id', 'inventory_items.product_id'))
            ->paginate(15);

        return view('inventory.index', compact('warehouse', 'cabinet', 'inventoryItems'));
    }

    public function create(Warehouse $warehouse, Cabinet $cabinet): View
    {
        return view('inventory.create', [
            'warehouse' => $warehouse,
            'cabinet' => $cabinet,
            'products' => Product::query()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreInventoryItemRequest $request, Warehouse $warehouse, Cabinet $cabinet): RedirectResponse
    {
        $cabinet->inventoryItems()->create($request->validated());

        return redirect()->route('warehouses.cabinets.inventory.index', [$warehouse, $cabinet])
            ->with('success', 'Producto agregado al inventario correctamente.');
    }

    public function edit(Warehouse $warehouse, Cabinet $cabinet, InventoryItem $inventoryItem): View
    {
        return view('inventory.edit', [
            'warehouse' => $warehouse,
            'cabinet' => $cabinet,
            'inventoryItem' => $inventoryItem,
            'products' => Product::query()->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateInventoryItemRequest $request, Warehouse $warehouse, Cabinet $cabinet, InventoryItem $inventoryItem): RedirectResponse
    {
        $inventoryItem->update($request->validated());

        return redirect()->route('warehouses.cabinets.inventory.index', [$warehouse, $cabinet])
            ->with('success', 'Configuración de inventario actualizada correctamente.');
    }

    public function destroy(Warehouse $warehouse, Cabinet $cabinet, InventoryItem $inventoryItem): RedirectResponse
    {
        if ($inventoryItem->adjustments()->exists()) {
            return redirect()->route('warehouses.cabinets.inventory.index', [$warehouse, $cabinet])
                ->with('error', 'No se puede retirar el producto porque tiene movimientos de inventario registrados.');
        }

        if ($inventoryItem->outgoingTransferItems()->exists() || $inventoryItem->incomingTransferItems()->exists()) {
            return redirect()->route('warehouses.cabinets.inventory.index', [$warehouse, $cabinet])
                ->with('error', 'No se puede retirar el producto porque tiene movimientos de inventario registrados.');
        }

        if ($inventoryItem->entryItems()->exists() || $inventoryItem->batches()->exists()) {
            return redirect()->route('warehouses.cabinets.inventory.index', [$warehouse, $cabinet])
                ->with('error', 'No se puede retirar el producto porque tiene existencias o entradas registradas.');
        }

        $inventoryItem->delete();

        return redirect()->route('warehouses.cabinets.inventory.index', [$warehouse, $cabinet])
            ->with('success', 'Producto retirado de la configuración del inventario.');
    }
}
