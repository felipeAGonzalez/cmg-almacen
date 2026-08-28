<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Cabinet;
use App\Models\Entry;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntryInterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_and_assigned_manager_have_visible_entry_navigation(): void
    {
        $warehouse = Warehouse::factory()->create(['name' => 'Almacén Centro']);
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->get(route('warehouses.index'))
            ->assertOk()
            ->assertSee(route('warehouses.entries.index', $warehouse), false)
            ->assertSee('Entradas');

        $manager = User::factory()->warehouseManager()->create();
        $manager->warehouses()->attach($warehouse);

        $this->actingAs($manager)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('MIS ALMACENES')
            ->assertSee(route('warehouses.entries.index', $warehouse), false);
    }

    public function test_nurse_does_not_see_entry_navigation(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::NURSE]))
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee('MIS ALMACENES')
            ->assertDontSee('Entradas');
    }

    public function test_index_displays_invoice_supplier_item_count_and_calculated_total(): void
    {
        [$warehouse, $supplier, $inventoryItem, $administrator] = $this->context();
        $this->createEntry($warehouse, $supplier, $inventoryItem, quantity: '3', cost: '10.2500');

        $this->actingAs($administrator)
            ->get(route('warehouses.entries.index', $warehouse))
            ->assertOk()
            ->assertSee('INV-001')
            ->assertSee($supplier->name)
            ->assertSee('1 partida')
            ->assertSee('$30.75')
            ->assertSee('Ver detalle')
            ->assertDontSee('Editar')
            ->assertDontSee('Eliminar');
    }

    public function test_create_only_displays_contextual_suppliers_and_main_inventory_items(): void
    {
        [$warehouse, $supplier, $inventoryItem, $administrator] = $this->context();
        $otherSupplier = Supplier::factory()->create(['name' => 'Proveedor externo']);
        $otherItem = InventoryItem::factory()->forWarehouse(Warehouse::factory()->create())->create();
        $cabinet = Cabinet::factory()->for($warehouse)->create();
        $cabinetItem = InventoryItem::factory()->forCabinet($cabinet)->create();

        $response = $this->actingAs($administrator)->get(route('warehouses.entries.create', $warehouse));

        $response->assertOk()
            ->assertSee($supplier->name)
            ->assertSee($inventoryItem->product->name)
            ->assertDontSee($otherSupplier->name)
            ->assertDontSee($otherItem->product->name)
            ->assertDontSee($cabinetItem->product->name)
            ->assertSee('items[0][inventory_item_id]', false)
            ->assertSee('items[0][quantity]', false)
            ->assertDontSee('name="total"', false)
            ->assertDontSee('name="subtotal"', false)
            ->assertDontSee('name="internal_lot"', false);
    }

    public function test_expiration_requirement_is_exposed_as_minimal_frontend_data(): void
    {
        [$warehouse, , , $administrator] = $this->context();
        $requiredProduct = Product::factory()->create(['requires_expiration' => true]);
        $requiredItem = InventoryItem::factory()->forWarehouse($warehouse)->for($requiredProduct)->create();

        $this->actingAs($administrator)
            ->get(route('warehouses.entries.create', $warehouse))
            ->assertOk()
            ->assertSee($requiredProduct->name)
            ->assertSee('requiresExpiration')
            ->assertSee('true')
            ->assertSee((string) $requiredItem->id);
    }

    public function test_validation_errors_preserve_multiple_submitted_items(): void
    {
        [$warehouse, $supplier, $firstItem, $administrator] = $this->context();
        $secondItem = InventoryItem::factory()->forWarehouse($warehouse)->create();

        $this->actingAs($administrator)
            ->followingRedirects()
            ->from(route('warehouses.entries.create', $warehouse))
            ->post(route('warehouses.entries.store', $warehouse), [
                'supplier_id' => $supplier->id,
                'invoice_number' => 'INV-ERROR',
                'invoice_date' => '2026-08-28',
                'items' => [
                    ['inventory_item_id' => $firstItem->id, 'quantity' => '2.5', 'unit_cost' => '4.1250'],
                    ['inventory_item_id' => $secondItem->id, 'quantity' => '0', 'unit_cost' => '10.25'],
                ],
            ])
            ->assertOk()
            ->assertSee('items[0][inventory_item_id]', false)
            ->assertSee('items[1][inventory_item_id]', false)
            ->assertSee('value="2.5"', false)
            ->assertSee('value="10.25"', false)
            ->assertSee('is-invalid', false)
            ->assertSee('La cantidad recibida debe ser mayor que cero.');
    }

    public function test_show_displays_historical_lots_expiration_and_exact_total_without_mutation_actions(): void
    {
        [$warehouse, $supplier, $inventoryItem, $administrator] = $this->context(requiresExpiration: true);
        $entry = $this->createEntry(
            $warehouse,
            $supplier,
            $inventoryItem,
            quantity: '2.500',
            cost: '4.1250',
            manufacturerLot: 'FAB-900',
            expirationDate: '2027-12-31',
        );
        $batch = $entry->items()->firstOrFail()->batch;

        $this->actingAs($administrator)
            ->get(route('warehouses.entries.show', [$warehouse, $entry]))
            ->assertOk()
            ->assertSee($batch->internal_lot)
            ->assertSee('FAB-900')
            ->assertSee('31/12/2027')
            ->assertSee('$10.31')
            ->assertSee('Volver a entradas')
            ->assertDontSee('Editar')
            ->assertDontSee('Eliminar');
    }

    public function test_show_uses_fallbacks_and_manager_breadcrumb_does_not_link_to_global_warehouses(): void
    {
        [$warehouse, $supplier, $inventoryItem] = $this->context();
        $entry = $this->createEntry($warehouse, $supplier, $inventoryItem);
        $manager = User::factory()->warehouseManager()->create();
        $manager->warehouses()->attach($warehouse);

        $this->actingAs($manager)
            ->get(route('warehouses.entries.show', [$warehouse, $entry]))
            ->assertOk()
            ->assertSee('Inicio')
            ->assertSee('No indicado')
            ->assertSee('No aplica')
            ->assertDontSee('href="'.route('warehouses.index').'"', false);
    }

    /** @return array{Warehouse, Supplier, InventoryItem, User} */
    private function context(bool $requiresExpiration = false): array
    {
        $warehouse = Warehouse::factory()->create();
        $supplier = Supplier::factory()->for($warehouse)->create();
        $product = Product::factory()->create(['requires_expiration' => $requiresExpiration]);
        $inventoryItem = InventoryItem::factory()->forWarehouse($warehouse)->for($product)->create();

        return [$warehouse, $supplier, $inventoryItem, User::factory()->administrator()->create()];
    }

    private function createEntry(
        Warehouse $warehouse,
        Supplier $supplier,
        InventoryItem $inventoryItem,
        string $quantity = '1.000',
        string $cost = '10.0000',
        ?string $manufacturerLot = null,
        ?string $expirationDate = null,
    ): Entry {
        $this->actingAs(User::factory()->administrator()->create())->post(
            route('warehouses.entries.store', $warehouse),
            [
                'supplier_id' => $supplier->id,
                'invoice_number' => 'INV-001',
                'invoice_date' => '2026-08-28',
                'notes' => null,
                'items' => [[
                    'inventory_item_id' => $inventoryItem->id,
                    'quantity' => $quantity,
                    'unit_cost' => $cost,
                    'manufacturer_lot' => $manufacturerLot,
                    'expiration_date' => $expirationDate,
                ]],
            ],
        )->assertSessionHasNoErrors();

        return Entry::query()->firstOrFail();
    }
}
