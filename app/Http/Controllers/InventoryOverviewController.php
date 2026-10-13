<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\InventoryFilterRequest;
use App\Models\Cabinet;
use App\Models\InventoryItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class InventoryOverviewController extends Controller
{
    public function __invoke(InventoryFilterRequest $request): View
    {
        $user = $request->user();

        abort_unless($user->isAdmin() || $user->role === UserRole::WAREHOUSE_MANAGER, 403);

        $query = InventoryItem::query();

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
        $inventoryItems = $query
            ->filterInventory($filters)
            ->withStockTotals()
            ->with(['product.unit', 'stockable' => fn ($morphTo) => $morphTo->morphWith([
                Cabinet::class => ['warehouse:id,name'],
            ])])
            ->orderBy(Product::query()->select('name')->whereColumn('products.id', 'inventory_items.product_id'))
            ->paginate(25)
            ->withQueryString();

        return view('inventory.overview', compact('inventoryItems', 'filters'));
    }
}
