<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWarehouseRequest;
use App\Http\Requests\UpdateWarehouseRequest;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    public function index(): View
    {
        $warehouses = Warehouse::query()
            ->withCount('users')
            ->orderBy('name')
            ->paginate(15);

        return view('warehouses.index', compact('warehouses'));
    }

    public function create(): View
    {
        return view('warehouses.create');
    }

    public function store(StoreWarehouseRequest $request): RedirectResponse
    {
        Warehouse::create($request->validated());

        return redirect()
            ->route('warehouses.index')
            ->with('success', 'Almacén creado correctamente.');
    }

    public function edit(Warehouse $warehouse): View
    {
        return view('warehouses.edit', compact('warehouse'));
    }

    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $warehouse->update($request->validated());

        return redirect()
            ->route('warehouses.index')
            ->with('success', 'Almacén actualizado correctamente.');
    }

    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        if ($warehouse->users()->exists()) {
            return redirect()
                ->route('warehouses.index')
                ->with('error', 'No se puede eliminar el almacén porque tiene usuarios asignados.');
        }

        if ($warehouse->suppliers()->exists()) {
            return redirect()
                ->route('warehouses.index')
                ->with('error', 'No se puede eliminar el almacén porque tiene proveedores registrados.');
        }

        $warehouse->delete();

        return redirect()
            ->route('warehouses.index')
            ->with('success', 'Almacén eliminado correctamente.');
    }
}
