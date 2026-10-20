<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEntryProductRequest;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class EntryProductController extends Controller
{
    public function __invoke(StoreEntryProductRequest $request, Warehouse $warehouse): JsonResponse
    {
        $data = $request->validated();

        $inventoryItem = DB::transaction(function () use ($data, $warehouse): InventoryItem {
            $product = Product::query()->create(Arr::only($data, [
                'name',
                'commercial_name',
                'code',
                'barcode',
                'unit_id',
                'category_id',
                'brand_id',
                'description',
                'requires_expiration',
            ]));

            return $warehouse->inventoryItems()->create([
                'product_id' => $product->id,
                'location_id' => $data['location_id'] ?? null,
                'minimum_stock' => $data['minimum_stock'],
                'maximum_stock' => $data['maximum_stock'],
            ]);
        });

        $inventoryItem->load(['product.unit', 'product.category']);
        $product = $inventoryItem->product;

        return response()->json([
            'message' => 'Producto creado y agregado al inventario correctamente.',
            'inventory_item' => [
                'id' => $inventoryItem->id,
                'name' => $product->name,
                'commercialName' => $product->commercial_name,
                'unit' => $product->unit->name,
                'identifier' => $product->code ?: $product->barcode,
                'searchText' => implode(' ', array_filter([
                    $product->name,
                    $product->commercial_name,
                    $product->code,
                    $product->barcode,
                ])),
                'requiresExpiration' => $product->requires_expiration,
                'isVatTaxable' => $product->isVatTaxable(),
            ],
        ], 201);
    }
}
