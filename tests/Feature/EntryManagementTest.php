<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Cabinet;
use App\Models\Entry;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EntryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_and_root_can_list_entries_from_any_warehouse(): void
    {
        $warehouse = Warehouse::factory()->create();

        foreach ([UserRole::ADMINISTRATOR, UserRole::ROOT] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('warehouses.entries.index', $warehouse))->assertOk();
        }
    }

    public function test_manager_access_is_limited_to_assigned_warehouses(): void
    {
        $manager = User::factory()->warehouseManager()->create();
        $assigned = Warehouse::factory()->create();
        $other = Warehouse::factory()->create();
        $manager->warehouses()->attach($assigned);

        $this->actingAs($manager)->get(route('warehouses.entries.index', $assigned))->assertOk();
        $this->get(route('warehouses.entries.index', $other))->assertForbidden();
    }

    public function test_nurse_legacy_user_and_guests_cannot_manage_entries(): void
    {
        $warehouse = Warehouse::factory()->create();

        foreach ([UserRole::NURSE, UserRole::LEGACY_USER] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('warehouses.entries.index', $warehouse))
                ->assertForbidden();
        }

        auth()->logout();
        $this->get(route('warehouses.entries.index', $warehouse))->assertRedirect(route('login'));
        $this->post(route('warehouses.entries.store', $warehouse))->assertRedirect(route('login'));
    }

    public function test_supplier_must_belong_to_the_route_warehouse(): void
    {
        [$warehouse, $supplier, $inventoryItem] = $this->context();
        $otherSupplier = Supplier::factory()->create();

        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('warehouses.entries.store', $warehouse), $this->payload($otherSupplier, $inventoryItem))
            ->assertSessionHasErrors('supplier_id');

        $this->assertDatabaseCount('entries', 0);
        $this->assertTrue($supplier->warehouse->is($warehouse));
    }

    public function test_inventory_item_must_be_from_main_inventory_of_route_warehouse(): void
    {
        [$warehouse, $supplier] = $this->context();
        $otherWarehouseItem = InventoryItem::factory()->forWarehouse(Warehouse::factory()->create())->create();
        $cabinet = Cabinet::factory()->for($warehouse)->create();
        $cabinetItem = InventoryItem::factory()->forCabinet($cabinet)->create();
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->post(route('warehouses.entries.store', $warehouse), $this->payload($supplier, $otherWarehouseItem))
            ->assertSessionHasErrors('items.0.inventory_item_id');

        $this->post(route('warehouses.entries.store', $warehouse), $this->payload($supplier, $cabinetItem))
            ->assertSessionHasErrors('items.0.inventory_item_id');

        $this->assertDatabaseCount('entries', 0);
    }

    public function test_valid_invoice_creates_entry_item_and_physical_batch(): void
    {
        [$warehouse, $supplier, $inventoryItem] = $this->context();

        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('warehouses.entries.store', $warehouse), $this->payload(
                $supplier,
                $inventoryItem,
                quantity: '2.500',
                unitCost: '4.1250',
                manufacturerLot: '  FAB-001  ',
            ))->assertSessionHasNoErrors();

        $entry = Entry::query()->firstOrFail();
        $item = $entry->items()->firstOrFail();
        $batch = $item->batch()->firstOrFail();

        $this->assertTrue($entry->warehouse->is($warehouse));
        $this->assertTrue($entry->supplier->is($supplier));
        $this->assertTrue($item->inventoryItem->is($inventoryItem));
        $this->assertSame('2.500', $item->quantity);
        $this->assertSame('4.1250', $item->unit_cost);
        $this->assertSame('FAB-001', $item->manufacturer_lot);
        $this->assertSame('FAB-001', $batch->manufacturer_lot);
        $this->assertSame('2.500', $batch->received_quantity);
        $this->assertSame('2.500', $batch->available_quantity);
        $this->assertSame('4.1250', $batch->unit_cost);
        $this->assertMatchesRegularExpression('/^INT-\d{8}-\d{6,}$/', $batch->internal_lot);
    }

    public function test_invoice_fields_and_at_least_one_item_are_required(): void
    {
        $warehouse = Warehouse::factory()->create();

        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('warehouses.entries.store', $warehouse), [])
            ->assertSessionHasErrors(['supplier_id', 'invoice_number', 'invoice_date', 'items']);
    }

    public function test_invoice_number_is_unique_per_warehouse_and_supplier(): void
    {
        [$warehouse, $supplier, $inventoryItem] = $this->context();
        $otherSupplier = Supplier::factory()->for($warehouse)->create();
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->post(route('warehouses.entries.store', $warehouse), $this->payload($supplier, $inventoryItem))
            ->assertSessionHasNoErrors();

        $this->post(route('warehouses.entries.store', $warehouse), $this->payload($supplier, $inventoryItem))
            ->assertSessionHasErrors('invoice_number');

        $this->post(route('warehouses.entries.store', $warehouse), $this->payload($otherSupplier, $inventoryItem))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('entries', 2);
    }

    public function test_quantity_and_unit_cost_rules_accept_valid_decimals(): void
    {
        [$warehouse, $supplier, $inventoryItem] = $this->context();
        $administrator = User::factory()->administrator()->create();

        foreach (['0', '-1'] as $quantity) {
            $this->actingAs($administrator)
                ->post(route('warehouses.entries.store', $warehouse), $this->payload($supplier, $inventoryItem, quantity: $quantity))
                ->assertSessionHasErrors('items.0.quantity');
        }

        $this->post(route('warehouses.entries.store', $warehouse), $this->payload($supplier, $inventoryItem, unitCost: '-0.0001'))
            ->assertSessionHasErrors('items.0.unit_cost');

        $this->post(route('warehouses.entries.store', $warehouse), $this->payload($supplier, $inventoryItem, quantity: '1.125', unitCost: '0'))
            ->assertSessionHasNoErrors();
    }

    public function test_manufacturer_lot_is_optional_and_is_never_replaced_with_generic_text(): void
    {
        [$warehouse, $supplier, $inventoryItem] = $this->context();

        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('warehouses.entries.store', $warehouse), $this->payload($supplier, $inventoryItem, manufacturerLot: '  '))
            ->assertSessionHasNoErrors();

        $item = Entry::query()->firstOrFail()->items()->firstOrFail();
        $this->assertNull($item->manufacturer_lot);
        $this->assertNull($item->batch->manufacturer_lot);
        $this->assertNotSame('GENÉRICO', $item->batch->manufacturer_lot);
    }

    public function test_expiration_date_depends_only_on_product_flag(): void
    {
        [$warehouse, $supplier, $requiredItem] = $this->context(requiresExpiration: true);
        $optionalProduct = Product::factory()->create(['requires_expiration' => false]);
        $optionalItem = InventoryItem::factory()->forWarehouse($warehouse)->for($optionalProduct)->create();
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->post(route('warehouses.entries.store', $warehouse), $this->payload($supplier, $requiredItem))
            ->assertSessionHasErrors('items.0.expiration_date');

        $this->post(route('warehouses.entries.store', $warehouse), $this->payload($supplier, $optionalItem))
            ->assertSessionHasNoErrors();

        $this->post(route('warehouses.entries.store', $warehouse), [
            ...$this->payload($supplier, $optionalItem, invoiceNumber: 'INV-002'),
            'items' => [[
                ...$this->payload($supplier, $optionalItem)['items'][0],
                'expiration_date' => '2027-12-31',
            ]],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('inventory_batches', ['expiration_date' => '2027-12-31 00:00:00']);
    }

    public function test_each_item_creates_a_batch_with_unique_internal_lot(): void
    {
        [$warehouse, $supplier, $firstItem] = $this->context();
        $secondItem = InventoryItem::factory()->forWarehouse($warehouse)->create();
        $payload = $this->payload($supplier, $firstItem);
        $payload['items'][] = [
            'inventory_item_id' => $secondItem->id,
            'quantity' => '3',
            'unit_cost' => '10.2500',
            'manufacturer_lot' => null,
            'expiration_date' => null,
        ];

        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('warehouses.entries.store', $warehouse), $payload)
            ->assertSessionHasNoErrors();

        $lots = InventoryBatch::query()->pluck('internal_lot');
        $this->assertCount(2, $lots);
        $this->assertCount(2, $lots->unique());
    }

    public function test_subtotals_and_total_use_exact_decimal_arithmetic(): void
    {
        [$warehouse, $supplier, $firstItem] = $this->context();
        $secondItem = InventoryItem::factory()->forWarehouse($warehouse)->create();
        $payload = $this->payload($supplier, $firstItem, quantity: '3', unitCost: '10.2500');
        $payload['items'][] = [
            'inventory_item_id' => $secondItem->id,
            'quantity' => '2.500',
            'unit_cost' => '4.1250',
            'manufacturer_lot' => null,
            'expiration_date' => null,
        ];

        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('warehouses.entries.store', $warehouse), $payload)
            ->assertSessionHasNoErrors();

        $entry = Entry::query()->with('items')->firstOrFail();
        $this->assertSame('30.7500000', $entry->items[0]->subtotal);
        $this->assertSame('10.3125000', $entry->items[1]->subtotal);
        $this->assertSame('41.0625000', $entry->total);
    }

    public function test_invalid_second_item_leaves_no_partial_records(): void
    {
        [$warehouse, $supplier, $inventoryItem] = $this->context();
        $payload = $this->payload($supplier, $inventoryItem);
        $payload['items'][] = [
            'inventory_item_id' => 999999,
            'quantity' => '1',
            'unit_cost' => '1',
        ];

        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('warehouses.entries.store', $warehouse), $payload)
            ->assertSessionHasErrors('items.1.inventory_item_id');

        $this->assertDatabaseCount('entries', 0);
        $this->assertDatabaseCount('entry_items', 0);
        $this->assertDatabaseCount('inventory_batches', 0);
    }

    public function test_create_and_show_use_only_contextual_suppliers_items_and_eager_relations(): void
    {
        [$warehouse, $supplier, $inventoryItem] = $this->context();
        $otherSupplier = Supplier::factory()->create(['name' => 'Proveedor ajeno']);
        $cabinetItem = InventoryItem::factory()->forCabinet(Cabinet::factory()->for($warehouse)->create())->create();
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->get(route('warehouses.entries.create', $warehouse))
            ->assertOk()
            ->assertViewHas('suppliers', fn ($suppliers): bool => $suppliers->contains($supplier) && ! $suppliers->contains($otherSupplier))
            ->assertViewHas('inventoryItems', fn ($items): bool => $items->contains($inventoryItem) && ! $items->contains($cabinetItem));

        $this->post(route('warehouses.entries.store', $warehouse), $this->payload($supplier, $inventoryItem));
        $entry = Entry::query()->firstOrFail();

        $this->get(route('warehouses.entries.show', [$warehouse, $entry]))
            ->assertOk()
            ->assertViewHas('entry', fn (Entry $model): bool => $model->relationLoaded('supplier')
                && $model->relationLoaded('items')
                && $model->items->first()->relationLoaded('batch')
                && $model->items->first()->inventoryItem->relationLoaded('product'));
    }

    public function test_scoped_binding_rejects_entry_from_another_warehouse(): void
    {
        [$warehouse, $supplier, $inventoryItem] = $this->context();
        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('warehouses.entries.store', $warehouse), $this->payload($supplier, $inventoryItem));
        $entry = Entry::query()->firstOrFail();
        $otherWarehouse = Warehouse::factory()->create();

        $this->get(route('warehouses.entries.show', [$otherWarehouse, $entry]))->assertNotFound();
    }

    public function test_database_restricts_direct_inventory_item_deletion(): void
    {
        [$warehouse, $supplier, $inventoryItem] = $this->context();
        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('warehouses.entries.store', $warehouse), $this->payload($supplier, $inventoryItem));

        $this->expectException(QueryException::class);
        $inventoryItem->delete();
    }

    public function test_entries_are_historical_and_have_no_update_or_destroy_routes(): void
    {
        $this->assertFalse(Route::has('warehouses.entries.edit'));
        $this->assertFalse(Route::has('warehouses.entries.update'));
        $this->assertFalse(Route::has('warehouses.entries.destroy'));
    }

    public function test_supplier_and_inventory_item_with_entry_dependencies_cannot_be_removed(): void
    {
        [$warehouse, $supplier, $inventoryItem] = $this->context();
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->post(route('warehouses.entries.store', $warehouse), $this->payload($supplier, $inventoryItem))
            ->assertSessionHasNoErrors();

        $this->delete(route('warehouses.suppliers.destroy', [$warehouse, $supplier]))
            ->assertSessionHas('error', 'No se puede eliminar el proveedor porque tiene entradas registradas.');
        $this->delete(route('warehouses.inventory.destroy', [$warehouse, $inventoryItem]))
            ->assertSessionHas('error', 'No se puede retirar el producto porque tiene existencias o entradas registradas.');

        $this->assertModelExists($supplier);
        $this->assertModelExists($inventoryItem);
        $this->assertDatabaseCount('inventory_batches', 1);
    }

    public function test_database_foreign_keys_preserve_historical_dependencies(): void
    {
        [$warehouse, $supplier, $inventoryItem] = $this->context();
        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('warehouses.entries.store', $warehouse), $this->payload($supplier, $inventoryItem));

        $this->expectException(QueryException::class);
        $supplier->delete();
    }

    /** @return array{Warehouse, Supplier, InventoryItem} */
    private function context(bool $requiresExpiration = false): array
    {
        $warehouse = Warehouse::factory()->create();
        $supplier = Supplier::factory()->for($warehouse)->create();
        $product = Product::factory()->create(['requires_expiration' => $requiresExpiration]);
        $inventoryItem = InventoryItem::factory()->forWarehouse($warehouse)->for($product)->create();

        return [$warehouse, $supplier, $inventoryItem];
    }

    /** @return array<string, mixed> */
    private function payload(
        Supplier $supplier,
        InventoryItem $inventoryItem,
        string $invoiceNumber = ' INV-001 ',
        string $quantity = '3',
        string $unitCost = '10.2500',
        ?string $manufacturerLot = null,
    ): array {
        return [
            'warehouse_id' => Warehouse::factory()->create()->id,
            'supplier_id' => $supplier->id,
            'invoice_number' => $invoiceNumber,
            'invoice_date' => '2026-08-28',
            'notes' => '  Recepción de prueba  ',
            'total' => '999999',
            'items' => [[
                'inventory_item_id' => $inventoryItem->id,
                'product_id' => $inventoryItem->product_id,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'manufacturer_lot' => $manufacturerLot,
                'expiration_date' => null,
                'internal_lot' => 'MANIPULATED',
                'received_quantity' => '999',
                'available_quantity' => '999',
                'subtotal' => '999',
            ]],
        ];
    }
}
