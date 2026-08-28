<?php

namespace Tests\Feature;

use App\Models\Cabinet;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryDependencyProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_with_inventory_items_cannot_be_deleted(): void
    {
        $item = InventoryItem::factory()->create();
        $product = $item->product;

        $this->actingAs(User::factory()->administrator()->create())
            ->delete(route('products.destroy', $product))
            ->assertRedirect(route('products.index'))
            ->assertSessionHas('error', 'No se puede eliminar el producto porque está registrado en inventarios.');

        $this->assertModelExists($product);
        $this->assertModelExists($item);
    }

    public function test_location_used_by_inventory_cannot_be_deleted(): void
    {
        $warehouse = Warehouse::factory()->create();
        $location = Location::factory()->for($warehouse)->create();
        $item = InventoryItem::factory()->forWarehouse($warehouse, $location)->create();

        $this->actingAs(User::factory()->administrator()->create())
            ->delete(route('warehouses.locations.destroy', [$warehouse, $location]))
            ->assertSessionHas('error', 'No se puede eliminar la ubicación porque está siendo utilizada en el inventario.');

        $this->assertModelExists($location);
        $this->assertModelExists($item);
    }

    public function test_cabinet_with_inventory_items_cannot_be_deleted(): void
    {
        $cabinet = Cabinet::factory()->create();
        $item = InventoryItem::factory()->forCabinet($cabinet)->create();

        $this->actingAs(User::factory()->administrator()->create())
            ->delete(route('warehouses.cabinets.destroy', [$cabinet->warehouse, $cabinet]))
            ->assertSessionHas('error', 'No se puede eliminar el gabinete porque tiene productos configurados en su inventario.');

        $this->assertModelExists($cabinet);
        $this->assertModelExists($item);
    }

    public function test_warehouse_with_own_inventory_items_cannot_be_deleted(): void
    {
        $warehouse = Warehouse::factory()->create();
        $item = InventoryItem::factory()->forWarehouse($warehouse)->create();

        $this->actingAs(User::factory()->administrator()->create())
            ->delete(route('warehouses.destroy', $warehouse))
            ->assertSessionHas('error', 'No se puede eliminar el almacén porque tiene productos configurados en su inventario.');

        $this->assertModelExists($warehouse);
        $this->assertModelExists($item);
    }

    public function test_real_foreign_keys_restrict_direct_product_and_location_deletion(): void
    {
        $warehouse = Warehouse::factory()->create();
        $location = Location::factory()->for($warehouse)->create();
        $item = InventoryItem::factory()->forWarehouse($warehouse, $location)->create();

        foreach ([
            fn () => DB::table('products')->where('id', $item->product_id)->delete(),
            fn () => DB::table('locations')->where('id', $location->id)->delete(),
        ] as $delete) {
            $restricted = false;

            try {
                $delete();
            } catch (QueryException) {
                $restricted = true;
            }

            $this->assertTrue($restricted);
        }

        $this->assertModelExists($item);
    }
}
