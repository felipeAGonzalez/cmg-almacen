<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Warehouse $warehouse): View
    {
        $suppliers = $warehouse->suppliers()
            ->orderBy('name')
            ->paginate(15);

        return view('suppliers.index', compact('warehouse', 'suppliers'));
    }

    public function create(Warehouse $warehouse): View
    {
        return view('suppliers.create', compact('warehouse'));
    }

    public function store(StoreSupplierRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $warehouse->suppliers()->create($request->validated());

        return redirect()
            ->route('warehouses.suppliers.index', $warehouse)
            ->with('success', 'Proveedor creado correctamente.');
    }

    public function edit(Warehouse $warehouse, Supplier $supplier): View
    {
        return view('suppliers.edit', compact('warehouse', 'supplier'));
    }

    public function update(UpdateSupplierRequest $request, Warehouse $warehouse, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        return redirect()
            ->route('warehouses.suppliers.index', $warehouse)
            ->with('success', 'Proveedor actualizado correctamente.');
    }

    public function destroy(Warehouse $warehouse, Supplier $supplier): RedirectResponse
    {
        $supplier->delete();

        return redirect()
            ->route('warehouses.suppliers.index', $warehouse)
            ->with('success', 'Proveedor eliminado correctamente.');
    }
}
