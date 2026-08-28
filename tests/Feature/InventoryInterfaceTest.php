<?php

namespace Tests\Feature;

use App\Models\Cabinet;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryInterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_warehouse_inventory_renders_configuration_without_physical_stock(): void
    {
        $warehouse = Warehouse::factory()->create(['name' => 'Almacén Centro']);
        $location = Location::factory()->for($warehouse)->create(['name' => 'Estante A']);
        $product = Product::factory()->create(['name' => 'Jeringa 10 ml', 'code' => 'JER-001', 'barcode' => '7501234567890']);
        $item = InventoryItem::factory()->forWarehouse($warehouse, $location)->for($product)->create([
            'minimum_stock' => 2.500,
            'maximum_stock' => 10.125,
        ]);

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('warehouses.inventory.index', $warehouse))
            ->assertOk()
            ->assertSee('Almacén: Almacén Centro')
            ->assertSee('Jeringa 10 ml')
            ->assertSee('Interno: JER-001')
            ->assertSee('Barras: 7501234567890')
            ->assertSee('Estante A')
            ->assertSee('2.5')
            ->assertSee('10.125')
            ->assertSee(route('warehouses.inventory.destroy', [$warehouse, $item]), false)
            ->assertSee('Retirar del inventario')
            ->assertDontSee('Existencia actual')
            ->assertDontSee('Cantidad disponible');
    }

    public function test_cabinet_inventory_renders_context_without_location_column(): void
    {
        $warehouse = Warehouse::factory()->create(['name' => 'Almacén Norte']);
        $cabinet = Cabinet::factory()->for($warehouse)->create(['name' => 'Gabinete de Enfermería']);
        $product = Product::factory()->create(['name' => 'Guantes']);
        $item = InventoryItem::factory()->forCabinet($cabinet)->for($product)->create([
            'minimum_stock' => 5,
            'maximum_stock' => 20,
        ]);

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('warehouses.cabinets.inventory.index', [$warehouse, $cabinet]))
            ->assertOk()
            ->assertSee('Inventario del gabinete')
            ->assertSee('Almacén: Almacén Norte')
            ->assertSee('Gabinete: Gabinete de Enfermería')
            ->assertSee('Guantes')
            ->assertSee(route('warehouses.cabinets.inventory.destroy', [$warehouse, $cabinet, $item]), false)
            ->assertSee('¿Retirar producto del gabinete?')
            ->assertDontSee('<th scope="col">Ubicación</th>', false);
    }

    public function test_create_forms_preserve_their_stockable_context(): void
    {
        $warehouse = Warehouse::factory()->create();
        $otherWarehouse = Warehouse::factory()->create();
        $location = Location::factory()->for($warehouse)->create(['name' => 'Refrigerador']);
        $otherLocation = Location::factory()->for($otherWarehouse)->create(['name' => 'Ubicación ajena']);
        $cabinet = Cabinet::factory()->for($warehouse)->create();
        Product::factory()->create(['name' => 'Solución salina', 'code' => 'SOL-001']);
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->get(route('warehouses.inventory.create', $warehouse))
            ->assertOk()
            ->assertSee('Agregar producto al inventario')
            ->assertSee('Solución salina — SOL-001')
            ->assertSee('name="location_id"', false)
            ->assertSee('Refrigerador')
            ->assertDontSee('Ubicación ajena');

        $this->get(route('warehouses.cabinets.inventory.create', [$warehouse, $cabinet]))
            ->assertOk()
            ->assertSee('Agregar producto al gabinete')
            ->assertDontSee('name="location_id"', false);
    }

    public function test_edit_form_keeps_product_identity_read_only(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['name' => 'Gasas estériles']);
        $item = InventoryItem::factory()->forWarehouse($warehouse)->for($product)->create([
            'minimum_stock' => 4.250,
            'maximum_stock' => 16.500,
        ]);

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('warehouses.inventory.edit', [$warehouse, $item]))
            ->assertOk()
            ->assertSee('Editar configuración de inventario')
            ->assertSee('Gasas estériles')
            ->assertSee('El producto no puede cambiarse desde esta pantalla.')
            ->assertSee('name="product_id"', false)
            ->assertDontSee('<select id="product_id"', false)
            ->assertSee('value="4.250"', false)
            ->assertSee('value="16.500"', false);
    }

    public function test_inventory_navigation_is_available_in_authorized_contexts(): void
    {
        $warehouse = Warehouse::factory()->create(['name' => 'Almacén Asignado']);
        $cabinet = Cabinet::factory()->for($warehouse)->create(['name' => 'Gabinete Uno']);
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->get(route('warehouses.index'))
            ->assertOk()
            ->assertSee(route('warehouses.inventory.index', $warehouse), false)
            ->assertSee('Inventario');

        $this->get(route('warehouses.cabinets.index', $warehouse))
            ->assertOk()
            ->assertSee(route('warehouses.cabinets.inventory.index', [$warehouse, $cabinet]), false);

        $manager = User::factory()->warehouseManager()->create();
        $manager->warehouses()->attach($warehouse);

        $this->actingAs($manager)
            ->get(route('warehouses.inventory.index', $warehouse))
            ->assertOk()
            ->assertSee('MIS ALMACENES')
            ->assertSee(route('warehouses.inventory.index', $warehouse), false)
            ->assertSee('Volver al inicio')
            ->assertDontSee('Volver a almacenes');
    }

    public function test_nurse_has_no_inventory_navigation(): void
    {
        $warehouse = Warehouse::factory()->create();
        $nurse = User::factory()->nurse()->create();
        $nurse->warehouses()->attach($warehouse);

        $this->actingAs($nurse)
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee('MIS ALMACENES')
            ->assertDontSee(route('warehouses.inventory.index', $warehouse), false);
    }
}
