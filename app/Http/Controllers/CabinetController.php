<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCabinetRequest;
use App\Http\Requests\UpdateCabinetRequest;
use App\Models\Cabinet;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CabinetController extends Controller
{
    public function index(Warehouse $warehouse): View
    {
        $cabinets = $warehouse->cabinets()->orderBy('name')->paginate(15);

        return view('cabinets.index', compact('warehouse', 'cabinets'));
    }

    public function create(Warehouse $warehouse): View
    {
        return view('cabinets.create', compact('warehouse'));
    }

    public function store(StoreCabinetRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $warehouse->cabinets()->create($request->validated());

        return redirect()->route('warehouses.cabinets.index', $warehouse)
            ->with('success', 'Gabinete creado correctamente.');
    }

    public function edit(Warehouse $warehouse, Cabinet $cabinet): View
    {
        return view('cabinets.edit', compact('warehouse', 'cabinet'));
    }

    public function update(UpdateCabinetRequest $request, Warehouse $warehouse, Cabinet $cabinet): RedirectResponse
    {
        $cabinet->update($request->validated());

        return redirect()->route('warehouses.cabinets.index', $warehouse)
            ->with('success', 'Gabinete actualizado correctamente.');
    }

    public function destroy(Warehouse $warehouse, Cabinet $cabinet): RedirectResponse
    {
        if ($cabinet->inventoryTransfers()->exists()) {
            return redirect()->route('warehouses.cabinets.index', $warehouse)
                ->with('error', 'No se puede eliminar el gabinete porque tiene transferencias registradas.');
        }

        if ($cabinet->inventoryItems()->exists()) {
            return redirect()->route('warehouses.cabinets.index', $warehouse)
                ->with('error', 'No se puede eliminar el gabinete porque tiene productos configurados en su inventario.');
        }

        $cabinet->delete();

        return redirect()->route('warehouses.cabinets.index', $warehouse)
            ->with('success', 'Gabinete eliminado correctamente.');
    }
}
