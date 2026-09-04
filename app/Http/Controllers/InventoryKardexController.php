<?php

namespace App\Http\Controllers;

use App\Http\Requests\InventoryKardexRequest;
use App\Models\Cabinet;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\InventoryKardexService;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\View\View;

class InventoryKardexController extends Controller
{
    public function __construct(private readonly InventoryKardexService $kardex) {}

    public function warehouse(InventoryKardexRequest $request, Warehouse $warehouse): View
    {
        $filters = $request->validated();

        return view('kardex.warehouse', [
            'warehouse' => $warehouse,
            'movements' => $this->kardex->forWarehouse($warehouse, $filters),
            'inventoryItems' => $this->inventoryItems($warehouse->inventoryItems()),
            'filters' => $filters,
            'movementTypes' => [
                InventoryKardexService::ENTRY => 'Entrada',
                InventoryKardexService::TRANSFER_OUT => 'Transferencia enviada',
                InventoryKardexService::MANUAL_OUTBOUND => 'Salida manual',
                InventoryKardexService::ADJUSTMENT_IN => 'Ajuste positivo',
                InventoryKardexService::ADJUSTMENT_OUT => 'Ajuste negativo',
                InventoryKardexService::NURSING_VOUCHER_WAREHOUSE_OUT => 'Vale de Enfermería — Salida de almacén',
            ],
        ]);
    }

    public function cabinet(InventoryKardexRequest $request, Warehouse $warehouse, Cabinet $cabinet): View
    {
        $filters = $request->validated();

        return view('kardex.cabinet', [
            'warehouse' => $warehouse,
            'cabinet' => $cabinet,
            'movements' => $this->kardex->forCabinet($warehouse, $cabinet, $filters),
            'inventoryItems' => $this->inventoryItems($cabinet->inventoryItems()),
            'filters' => $filters,
            'movementTypes' => [
                InventoryKardexService::TRANSFER_IN => 'Transferencia recibida',
                InventoryKardexService::ADJUSTMENT_IN => 'Ajuste positivo',
                InventoryKardexService::ADJUSTMENT_OUT => 'Ajuste negativo',
                InventoryKardexService::NURSING_VOUCHER_CABINET_OUT => 'Vale de Enfermería — Salida de gabinete',
            ],
        ]);
    }

    private function inventoryItems(MorphMany $query)
    {
        return $query
            ->with('product.unit')
            ->orderBy(Product::query()->select('name')->whereColumn('products.id', 'inventory_items.product_id'))
            ->get();
    }
}
