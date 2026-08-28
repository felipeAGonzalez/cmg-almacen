<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLocationRequest;
use App\Http\Requests\UpdateLocationRequest;
use App\Models\Location;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LocationController extends Controller
{
    public function index(Warehouse $warehouse): View
    {
        $locations = $warehouse->locations()->orderBy('name')->paginate(15);

        return view('locations.index', compact('warehouse', 'locations'));
    }

    public function create(Warehouse $warehouse): View
    {
        return view('locations.create', compact('warehouse'));
    }

    public function store(StoreLocationRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $warehouse->locations()->create($request->validated());

        return redirect()->route('warehouses.locations.index', $warehouse)
            ->with('success', 'Ubicación creada correctamente.');
    }

    public function edit(Warehouse $warehouse, Location $location): View
    {
        return view('locations.edit', compact('warehouse', 'location'));
    }

    public function update(UpdateLocationRequest $request, Warehouse $warehouse, Location $location): RedirectResponse
    {
        $location->update($request->validated());

        return redirect()->route('warehouses.locations.index', $warehouse)
            ->with('success', 'Ubicación actualizada correctamente.');
    }

    public function destroy(Warehouse $warehouse, Location $location): RedirectResponse
    {
        if ($location->inventoryItems()->exists()) {
            return redirect()->route('warehouses.locations.index', $warehouse)
                ->with('error', 'No se puede eliminar la ubicación porque está siendo utilizada en el inventario.');
        }

        $location->delete();

        return redirect()->route('warehouses.locations.index', $warehouse)
            ->with('success', 'Ubicación eliminada correctamente.');
    }
}
