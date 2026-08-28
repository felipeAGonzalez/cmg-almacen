<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Cabinet;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InventoryItemManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_relationships_and_stable_morph_aliases_work(): void
    {
        $warehouse = Warehouse::factory()->create();
        $cabinet = Cabinet::factory()->for($warehouse)->create();
        $location = Location::factory()->for($warehouse)->create();
        $product = Product::factory()->create();
        $warehouseItem = InventoryItem::factory()->forWarehouse($warehouse, $location)->for($product)->create();
        $cabinetItem = InventoryItem::factory()->forCabinet($cabinet)->for($product)->create();

        $this->assertTrue($warehouseItem->stockable->is($warehouse));
        $this->assertTrue($cabinetItem->stockable->is($cabinet));
        $this->assertTrue($warehouseItem->product->is($product));
        $this->assertTrue($warehouseItem->location->is($location));
        $this->assertTrue($warehouse->inventoryItems->contains($warehouseItem));
        $this->assertTrue($cabinet->inventoryItems->contains($cabinetItem));
        $this->assertTrue($product->inventoryItems->contains($warehouseItem));
        $this->assertTrue($location->inventoryItems->contains($warehouseItem));
        $this->assertSame('warehouse', $warehouseItem->stockable_type);
        $this->assertSame('cabinet', $cabinetItem->stockable_type);
    }

    public function test_inventory_schema_does_not_contain_physical_quantity_fields(): void
    {
        foreach (['quantity', 'available_quantity', 'reserved_quantity', 'lot', 'batch', 'expiration_date', 'purchase_price', 'status'] as $column) {
            $this->assertFalse(Schema::hasColumn('inventory_items', $column));
        }
    }

    public function test_product_is_unique_per_stockable_but_reusable_elsewhere(): void
    {
        $product = Product::factory()->create();
        $firstWarehouse = Warehouse::factory()->create();
        $secondWarehouse = Warehouse::factory()->create();
        $firstCabinet = Cabinet::factory()->for($firstWarehouse)->create();
        $secondCabinet = Cabinet::factory()->for($secondWarehouse)->create();

        InventoryItem::factory()->forWarehouse($firstWarehouse)->for($product)->create();

        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('warehouses.inventory.store', $firstWarehouse), $this->payload($product))
            ->assertSessionHasErrors('product_id');

        $secondWarehouse->inventoryItems()->create($this->payload($product));
        $firstCabinet->inventoryItems()->create($this->payload($product));
        $secondCabinet->inventoryItems()->create($this->payload($product));

        $this->assertCount(4, $product->inventoryItems);
    }

    public function test_database_unique_index_rejects_duplicate_product_in_same_stockable(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        InventoryItem::factory()->forWarehouse($warehouse)->for($product)->create();

        $this->expectException(QueryException::class);
        InventoryItem::factory()->forWarehouse($warehouse)->for($product)->create();
    }

    public function test_stock_limits_are_required_non_negative_ordered_and_decimal(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->post(route('warehouses.inventory.store', $warehouse), ['product_id' => $product->id])
            ->assertSessionHasErrors(['minimum_stock', 'maximum_stock']);

        $this->post(route('warehouses.inventory.store', $warehouse), $this->payload($product, -1, 5))
            ->assertSessionHasErrors('minimum_stock');
        $this->post(route('warehouses.inventory.store', $warehouse), $this->payload($product, 5, 5))
            ->assertSessionHasErrors('maximum_stock');
        $this->post(route('warehouses.inventory.store', $warehouse), $this->payload($product, 6, 5))
            ->assertSessionHasErrors('maximum_stock');

        $this->post(route('warehouses.inventory.store', $warehouse), $this->payload($product, 1.125, 2.375))
            ->assertSessionHasNoErrors();

        $item = $warehouse->inventoryItems()->firstOrFail();
        $this->assertSame('1.125', $item->minimum_stock);
        $this->assertSame('2.375', $item->maximum_stock);
    }

    public function test_warehouse_inventory_accepts_only_locations_from_same_warehouse(): void
    {
        $warehouse = Warehouse::factory()->create();
        $location = Location::factory()->for($warehouse)->create();
        $otherLocation = Location::factory()->create();
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->post(route('warehouses.inventory.store', $warehouse), [
                ...$this->payload(Product::factory()->create()),
                'location_id' => $location->id,
            ])->assertSessionHasNoErrors();

        $this->post(route('warehouses.inventory.store', $warehouse), [
            ...$this->payload(Product::factory()->create()),
            'location_id' => $otherLocation->id,
        ])->assertSessionHasErrors('location_id');

        $this->post(route('warehouses.inventory.store', $warehouse), $this->payload(Product::factory()->create()))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('inventory_items', ['stockable_type' => 'warehouse', 'stockable_id' => $warehouse->id, 'location_id' => null]);
    }

    public function test_cabinet_inventory_rejects_location(): void
    {
        $cabinet = Cabinet::factory()->create();
        $location = Location::factory()->for($cabinet->warehouse)->create();

        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('warehouses.cabinets.inventory.store', [$cabinet->warehouse, $cabinet]), [
                ...$this->payload(Product::factory()->create()),
                'location_id' => $location->id,
            ])->assertSessionHasErrors('location_id');

        $this->assertDatabaseCount('inventory_items', 0);
    }

    public function test_route_context_ignores_manipulated_stockable_values(): void
    {
        $warehouse = Warehouse::factory()->create();
        $other = Warehouse::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('warehouses.inventory.store', $warehouse), [
                ...$this->payload($product),
                'stockable_type' => 'cabinet',
                'stockable_id' => $other->id,
            ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('inventory_items', [
            'stockable_type' => 'warehouse',
            'stockable_id' => $warehouse->id,
            'product_id' => $product->id,
        ]);
        $this->assertDatabaseMissing('inventory_items', ['stockable_type' => 'cabinet', 'stockable_id' => $other->id]);
    }

    public function test_administrator_and_root_can_manage_any_inventory(): void
    {
        foreach ([UserRole::ADMINISTRATOR, UserRole::ROOT] as $role) {
            $warehouse = Warehouse::factory()->create();
            $cabinet = Cabinet::factory()->for($warehouse)->create();
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get(route('warehouses.inventory.index', $warehouse))->assertOk();
            $this->get(route('warehouses.cabinets.inventory.index', [$warehouse, $cabinet]))->assertOk();
        }
    }

    public function test_manager_can_manage_assigned_warehouse_and_its_cabinets_only(): void
    {
        $manager = User::factory()->warehouseManager()->create();
        $assigned = Warehouse::factory()->create();
        $other = Warehouse::factory()->create();
        $assignedCabinet = Cabinet::factory()->for($assigned)->create();
        $otherCabinet = Cabinet::factory()->for($other)->create();
        $manager->warehouses()->attach($assigned);

        $this->actingAs($manager)->get(route('warehouses.inventory.index', $assigned))->assertOk();
        $this->get(route('warehouses.inventory.index', $other))->assertForbidden();
        $this->get(route('warehouses.cabinets.inventory.index', [$assigned, $assignedCabinet]))->assertOk();
        $this->get(route('warehouses.cabinets.inventory.index', [$other, $otherCabinet]))->assertForbidden();

        $this->post(route('warehouses.inventory.store', $assigned), $this->payload(Product::factory()->create()))
            ->assertSessionHasNoErrors();
        $this->post(route('warehouses.cabinets.inventory.store', [$assigned, $assignedCabinet]), $this->payload(Product::factory()->create()))
            ->assertSessionHasNoErrors();
    }

    public function test_nurse_and_legacy_user_cannot_manage_inventory(): void
    {
        $warehouse = Warehouse::factory()->create();

        foreach ([UserRole::NURSE, UserRole::LEGACY_USER] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $user->warehouses()->attach($warehouse);

            $this->actingAs($user)->get(route('warehouses.inventory.index', $warehouse))->assertForbidden();
        }
    }

    public function test_guests_are_redirected_from_inventory_routes(): void
    {
        $warehouse = Warehouse::factory()->create();
        $cabinet = Cabinet::factory()->for($warehouse)->create();

        $this->get(route('warehouses.inventory.index', $warehouse))->assertRedirect(route('login'));
        $this->post(route('warehouses.inventory.store', $warehouse))->assertRedirect(route('login'));
        $this->get(route('warehouses.cabinets.inventory.index', [$warehouse, $cabinet]))->assertRedirect(route('login'));
        $this->post(route('warehouses.cabinets.inventory.store', [$warehouse, $cabinet]))->assertRedirect(route('login'));
    }

    public function test_scoped_bindings_reject_mismatched_cabinet_and_inventory_items(): void
    {
        $warehouse = Warehouse::factory()->create();
        $otherWarehouse = Warehouse::factory()->create();
        $cabinet = Cabinet::factory()->for($warehouse)->create();
        $otherCabinet = Cabinet::factory()->for($otherWarehouse)->create();
        $warehouseItem = InventoryItem::factory()->forWarehouse($warehouse)->create();
        $otherWarehouseItem = InventoryItem::factory()->forWarehouse($otherWarehouse)->create();
        $cabinetItem = InventoryItem::factory()->forCabinet($cabinet)->create();
        $otherCabinetItem = InventoryItem::factory()->forCabinet($otherCabinet)->create();
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->get(route('warehouses.cabinets.inventory.index', [$warehouse, $otherCabinet]))
            ->assertNotFound();
        $this->get(route('warehouses.inventory.edit', [$warehouse, $otherWarehouseItem]))->assertNotFound();
        $this->get(route('warehouses.cabinets.inventory.edit', [$warehouse, $cabinet, $otherCabinetItem]))->assertNotFound();

        $this->get(route('warehouses.inventory.edit', [$warehouse, $warehouseItem]))->assertOk();
        $this->get(route('warehouses.cabinets.inventory.edit', [$warehouse, $cabinet, $cabinetItem]))->assertOk();
    }

    public function test_administrator_can_crud_warehouse_inventory(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->post(route('warehouses.inventory.store', $warehouse), $this->payload($product))
            ->assertRedirect(route('warehouses.inventory.index', $warehouse))
            ->assertSessionHas('success', 'Producto agregado al inventario correctamente.');

        $item = $warehouse->inventoryItems()->firstOrFail();

        $this->put(route('warehouses.inventory.update', [$warehouse, $item]), $this->payload($product, 5, 25))
            ->assertSessionHas('success', 'Configuración de inventario actualizada correctamente.');
        $this->assertSame('25.000', $item->fresh()->maximum_stock);

        $this->delete(route('warehouses.inventory.destroy', [$warehouse, $item]))
            ->assertSessionHas('success', 'Producto retirado de la configuración del inventario.');
        $this->assertModelMissing($item);
    }

    public function test_administrator_can_crud_cabinet_inventory(): void
    {
        $cabinet = Cabinet::factory()->create();
        $product = Product::factory()->create();
        $administrator = User::factory()->administrator()->create();
        $route = [$cabinet->warehouse, $cabinet];

        $this->actingAs($administrator)
            ->post(route('warehouses.cabinets.inventory.store', $route), $this->payload($product))
            ->assertRedirect(route('warehouses.cabinets.inventory.index', $route));

        $item = $cabinet->inventoryItems()->firstOrFail();

        $this->put(route('warehouses.cabinets.inventory.update', [...$route, $item]), $this->payload($product, 2, 12))
            ->assertSessionHas('success', 'Configuración de inventario actualizada correctamente.');
        $this->assertNull($item->fresh()->location_id);

        $this->delete(route('warehouses.cabinets.inventory.destroy', [...$route, $item]))
            ->assertSessionHas('success', 'Producto retirado de la configuración del inventario.');
        $this->assertModelMissing($item);
    }

    public function test_indexes_are_ordered_eager_loaded_and_forms_receive_contextual_data(): void
    {
        $warehouse = Warehouse::factory()->create();
        $location = Location::factory()->for($warehouse)->create(['name' => 'Estante A']);
        $otherLocation = Location::factory()->create(['name' => 'No disponible']);
        $lastProduct = Product::factory()->create(['name' => 'Zeta']);
        $firstProduct = Product::factory()->create(['name' => 'Alpha']);
        $last = InventoryItem::factory()->forWarehouse($warehouse)->for($lastProduct)->create();
        $first = InventoryItem::factory()->forWarehouse($warehouse, $location)->for($firstProduct)->create();
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->get(route('warehouses.inventory.index', $warehouse))
            ->assertViewHas('inventoryItems', function ($items) use ($first, $last): bool {
                $collection = $items->getCollection();

                return $collection->pluck('id')->all() === [$first->id, $last->id]
                    && $collection->every(fn (InventoryItem $item): bool => $item->relationLoaded('product')
                        && $item->product->relationLoaded('unit')
                        && $item->relationLoaded('location'));
            });

        $this->get(route('warehouses.inventory.create', $warehouse))
            ->assertViewHas('locations', fn ($locations): bool => $locations->pluck('id')->contains($location->id)
                && ! $locations->pluck('id')->contains($otherLocation->id))
            ->assertViewHas('products');

        $cabinet = Cabinet::factory()->for($warehouse)->create();
        $this->get(route('warehouses.cabinets.inventory.create', [$warehouse, $cabinet]))
            ->assertViewHas('products')
            ->assertViewMissing('locations');
    }

    /** @return array<string, int|float> */
    private function payload(Product $product, int|float $minimum = 1, int|float $maximum = 10): array
    {
        return [
            'product_id' => $product->id,
            'minimum_stock' => $minimum,
            'maximum_stock' => $maximum,
        ];
    }
}
