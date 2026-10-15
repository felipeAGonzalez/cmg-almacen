<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\InventoryFilterRequest;
use App\Models\Cabinet;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InventoryOverviewController extends Controller
{
    public function __invoke(InventoryFilterRequest $request): View
    {
        $user = $request->user();

        abort_unless($user->isAdmin() || $user->role === UserRole::WAREHOUSE_MANAGER, 403);

        $query = InventoryItem::query();
        $warehouses = $user->isAdmin()
            ? Warehouse::query()->with(['cabinets' => fn ($query) => $query->orderBy('name')])->orderBy('name')->get()
            : $user->warehouses()->with(['cabinets' => fn ($query) => $query->orderBy('name')])->orderBy('name')->get();

        if (! $user->isAdmin()) {
            $warehouseIds = $user->warehouses()->select('warehouses.id');
            $query->where(function (Builder $inventory) use ($warehouseIds): void {
                $inventory->where(fn (Builder $warehouse) => $warehouse
                    ->where('stockable_type', 'warehouse')
                    ->whereIn('stockable_id', clone $warehouseIds))
                    ->orWhere(fn (Builder $cabinet) => $cabinet
                        ->where('stockable_type', 'cabinet')
                        ->whereIn('stockable_id', Cabinet::query()->select('id')->whereIn('warehouse_id', clone $warehouseIds)));
            });
        }

        $filters = $request->validated();
        $inventoryContexts = $this->inventoryContexts($warehouses);
        if ($scope = $filters['inventory_scope'] ?? null) {
            if (! $inventoryContexts->has($scope)) {
                throw ValidationException::withMessages(['inventory_scope' => 'El inventario seleccionado no está disponible para tu usuario.']);
            }

            [$stockableType, $stockableId] = explode(':', $scope, 2);
            $query->where('stockable_type', $stockableType)->where('stockable_id', (int) $stockableId);
        }
        $inventoryItems = $query
            ->filterInventory($filters)
            ->withStockTotals()
            ->with(['product.unit', 'stockable' => fn ($morphTo) => $morphTo->morphWith([
                Cabinet::class => ['warehouse:id,name'],
            ])])
            ->orderBy(Product::query()->select('name')->whereColumn('products.id', 'inventory_items.product_id'))
            ->paginate(25)
            ->withQueryString();

        return view('inventory.overview', compact('inventoryItems', 'filters', 'inventoryContexts'));
    }

    private function inventoryContexts(Collection $warehouses): Collection
    {
        return $warehouses->flatMap(function (Warehouse $warehouse): array {
            $contexts = [
                'warehouse:'.$warehouse->id => 'Almacén — '.$warehouse->name,
            ];

            foreach ($warehouse->cabinets as $cabinet) {
                $contexts['cabinet:'.$cabinet->id] = 'Gabinete — '.$warehouse->name.' / '.$cabinet->name;
            }

            return $contexts;
        });
    }
}
