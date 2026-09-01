<?php

namespace App\Http\Controllers;

use App\Enums\InventoryAdjustmentReason;
use App\Http\Requests\StoreInventoryAdjustmentRequest;
use App\Models\Cabinet;
use App\Models\InventoryAdjustment;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InventoryAdjustmentController extends Controller
{
    public function index(Warehouse $warehouse, InventoryItem $inventoryItem): View
    {
        $adjustments = $inventoryItem->adjustments()
            ->with(['inventoryBatch', 'adjustedBy'])
            ->orderByDesc('adjusted_at')
            ->orderByDesc('id')
            ->paginate(15);

        return view('adjustments.index', $this->viewData($warehouse, $inventoryItem, compact('adjustments')));
    }

    public function create(Warehouse $warehouse, InventoryItem $inventoryItem): View
    {
        $batches = $inventoryItem->batches()->orderBy('created_at')->orderBy('id')->get();

        return view('adjustments.create', $this->viewData($warehouse, $inventoryItem, [
            'batches' => $batches,
            'batchOptions' => $batches->map(fn (InventoryBatch $batch): array => [
                'id' => $batch->id,
                'internalLot' => $batch->internal_lot,
                'manufacturerLot' => $batch->manufacturer_lot,
                'expirationDate' => $batch->expiration_date?->format('Y-m-d'),
                'availableQuantity' => $batch->available_quantity,
                'expired' => $batch->expiration_date?->isBefore(today()) ?? false,
            ])->values(),
            'reasons' => InventoryAdjustmentReason::cases(),
        ]));
    }

    public function store(StoreInventoryAdjustmentRequest $request, Warehouse $warehouse, InventoryItem $inventoryItem): RedirectResponse
    {
        $data = $request->validated();

        $adjustment = DB::transaction(function () use ($data, $request, $inventoryItem): InventoryAdjustment {
            $batch = InventoryBatch::query()
                ->whereKey($data['inventory_batch_id'])
                ->where('inventory_item_id', $inventoryItem->id)
                ->lockForUpdate()
                ->firstOrFail();

            $previousQuantity = bcadd($batch->available_quantity, '0', 3);
            $countedQuantity = bcadd((string) $data['counted_quantity'], '0', 3);
            $difference = bcsub($countedQuantity, $previousQuantity, 3);

            if (bccomp($difference, '0', 3) === 0) {
                throw ValidationException::withMessages([
                    'counted_quantity' => 'La cantidad contada coincide con la existencia registrada.',
                ]);
            }

            $adjustment = $inventoryItem->adjustments()->create([
                'inventory_batch_id' => $batch->id,
                'previous_quantity' => $previousQuantity,
                'counted_quantity' => $countedQuantity,
                'difference' => $difference,
                'reason' => $data['reason'],
                'notes' => $data['notes'],
                'adjusted_by' => $request->user()->id,
                'adjusted_at' => now(),
            ]);

            $batch->update(['available_quantity' => $countedQuantity]);

            return $adjustment;
        });

        return redirect()->route($this->routeName('show'), $this->routeParameters($warehouse, $inventoryItem, $adjustment))
            ->with('success', 'Ajuste de inventario registrado correctamente.');
    }

    public function show(Warehouse $warehouse, InventoryItem $inventoryItem, InventoryAdjustment $inventoryAdjustment): View
    {
        $inventoryAdjustment->load(['inventoryBatch', 'adjustedBy']);

        return view('adjustments.show', $this->viewData($warehouse, $inventoryItem, [
            'adjustment' => $inventoryAdjustment,
        ]));
    }

    public function cabinetIndex(Warehouse $warehouse, Cabinet $cabinet, InventoryItem $inventoryItem): View
    {
        return $this->index($warehouse, $inventoryItem);
    }

    public function cabinetCreate(Warehouse $warehouse, Cabinet $cabinet, InventoryItem $inventoryItem): View
    {
        return $this->create($warehouse, $inventoryItem);
    }

    public function cabinetStore(StoreInventoryAdjustmentRequest $request, Warehouse $warehouse, Cabinet $cabinet, InventoryItem $inventoryItem): RedirectResponse
    {
        return $this->store($request, $warehouse, $inventoryItem);
    }

    public function cabinetShow(Warehouse $warehouse, Cabinet $cabinet, InventoryItem $inventoryItem, InventoryAdjustment $inventoryAdjustment): View
    {
        return $this->show($warehouse, $inventoryItem, $inventoryAdjustment);
    }

    private function viewData(Warehouse $warehouse, InventoryItem $inventoryItem, array $data): array
    {
        $inventoryItem->loadMissing('product.unit');

        return [
            'warehouse' => $warehouse,
            'cabinet' => request()->route('cabinet'),
            'inventoryItem' => $inventoryItem,
            ...$data,
        ];
    }

    private function routeName(string $action): string
    {
        return request()->route('cabinet') instanceof Cabinet
            ? "warehouses.cabinets.inventory.adjustments.$action"
            : "warehouses.inventory.adjustments.$action";
    }

    private function routeParameters(Warehouse $warehouse, InventoryItem $inventoryItem, ?InventoryAdjustment $adjustment = null): array
    {
        $parameters = [$warehouse];
        if (request()->route('cabinet') instanceof Cabinet) {
            $parameters[] = request()->route('cabinet');
        }
        $parameters[] = $inventoryItem;
        if ($adjustment) {
            $parameters[] = $adjustment;
        }

        return $parameters;
    }
}
