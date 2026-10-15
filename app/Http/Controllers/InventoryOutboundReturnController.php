<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryOutboundReturnRequest;
use App\Models\InventoryOutbound;
use App\Models\InventoryOutboundReturn;
use App\Models\Warehouse;
use App\Services\InventoryOutboundReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryOutboundReturnController extends Controller
{
    public function create(Request $request, Warehouse $warehouse): View
    {
        $filters = $request->validate([
            'patient' => ['nullable', 'string', 'max:255'],
            'date' => ['nullable', 'date'],
            'outbound' => ['nullable', 'integer'],
        ]);

        $patient = trim((string) ($filters['patient'] ?? ''));
        $date = $filters['date'] ?? null;
        $outbounds = $warehouse->inventoryOutbounds()
            ->with('processedBy')
            ->when($patient !== '', fn ($query) => $query->where('patient_name', 'like', '%'.$patient.'%'))
            ->when($date, fn ($query, string $value) => $query->whereDate('processed_at', $value))
            ->orderByDesc('processed_at')
            ->limit(50)
            ->get();
        $selectedOutbound = $request->integer('outbound')
            ? $warehouse->inventoryOutbounds()->with(['items.inventoryItem.product.unit', 'items.allocations.returnItems', 'items.allocations.inventoryBatch'])->find($request->integer('outbound'))
            : null;

        return view('entries.returns.create', compact('warehouse', 'outbounds', 'selectedOutbound', 'patient', 'date'));
    }

    public function store(StoreInventoryOutboundReturnRequest $request, Warehouse $warehouse, InventoryOutboundReturnService $service): RedirectResponse
    {
        $data = $request->validated();
        $outbound = InventoryOutbound::findOrFail($data['inventory_outbound_id']);
        $return = $service->create($warehouse, $outbound, $request->user(), $data['items'], $data['received_at'], $data['notes'] ?? null);

        return redirect()->route('warehouses.entries.returns.show', [$warehouse, $return])->with('success', 'Devolución manual registrada correctamente.');
    }

    public function show(Warehouse $warehouse, InventoryOutboundReturn $inventoryOutboundReturn): View
    {
        abort_unless($inventoryOutboundReturn->warehouse_id === $warehouse->id, 404);
        $inventoryOutboundReturn->load(['outbound', 'receivedBy', 'items.allocation.outboundItem.inventoryItem.product.unit', 'items.allocation.inventoryBatch']);

        return view('entries.returns.show', ['warehouse' => $warehouse, 'return' => $inventoryOutboundReturn]);
    }
}
