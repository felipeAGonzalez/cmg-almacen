<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_and_root_can_list_suppliers_from_any_warehouse(): void
    {
        $warehouse = Warehouse::factory()->create();

        foreach ([UserRole::ADMINISTRATOR, UserRole::ROOT] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('warehouses.suppliers.index', $warehouse))
                ->assertOk()
                ->assertViewIs('suppliers.index')
                ->assertViewHasAll(['warehouse', 'suppliers']);
        }
    }

    public function test_manager_access_is_limited_to_assigned_warehouses(): void
    {
        $manager = User::factory()->warehouseManager()->create();
        $assigned = Warehouse::factory()->create();
        $other = Warehouse::factory()->create();
        $manager->warehouses()->attach($assigned);

        $this->actingAs($manager)->get(route('warehouses.suppliers.index', $assigned))->assertOk();
        $this->get(route('warehouses.suppliers.index', $other))->assertForbidden();
        $this->post(route('warehouses.suppliers.store', $assigned), $this->payload(['name' => 'Permitido']))->assertSessionHasNoErrors();
        $this->post(route('warehouses.suppliers.store', $other), $this->payload(['name' => 'Bloqueado']))->assertForbidden();

        $this->assertDatabaseHas('suppliers', ['warehouse_id' => $assigned->id, 'name' => 'Permitido']);
        $this->assertDatabaseMissing('suppliers', ['warehouse_id' => $other->id, 'name' => 'Bloqueado']);
    }

    public function test_nurse_and_legacy_user_cannot_manage_suppliers(): void
    {
        $warehouse = Warehouse::factory()->create();

        foreach ([UserRole::NURSE, UserRole::LEGACY_USER] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $user->warehouses()->attach($warehouse);
            $this->actingAs($user)->get(route('warehouses.suppliers.index', $warehouse))->assertForbidden();
        }
    }

    public function test_guests_are_redirected_from_all_supplier_routes(): void
    {
        $warehouse = Warehouse::factory()->create();
        $supplier = Supplier::factory()->for($warehouse)->create();

        $this->get(route('warehouses.suppliers.index', $warehouse))->assertRedirect(route('login'));
        $this->get(route('warehouses.suppliers.create', $warehouse))->assertRedirect(route('login'));
        $this->post(route('warehouses.suppliers.store', $warehouse))->assertRedirect(route('login'));
        $this->get(route('warehouses.suppliers.edit', [$warehouse, $supplier]))->assertRedirect(route('login'));
        $this->put(route('warehouses.suppliers.update', [$warehouse, $supplier]))->assertRedirect(route('login'));
        $this->delete(route('warehouses.suppliers.destroy', [$warehouse, $supplier]))->assertRedirect(route('login'));
    }

    public function test_administrator_can_create_a_supplier(): void
    {
        $administrator = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create();

        $this->actingAs($administrator)
            ->post(route('warehouses.suppliers.store', $warehouse), $this->payload())
            ->assertRedirect(route('warehouses.suppliers.index', $warehouse))
            ->assertSessionHas('success', 'Proveedor creado correctamente.');

        $this->assertDatabaseHas('suppliers', ['warehouse_id' => $warehouse->id, 'name' => 'Proveedor Médico ABC']);
    }

    public function test_creation_validation_and_normalization_are_applied(): void
    {
        $administrator = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create();

        $this->actingAs($administrator)
            ->post(route('warehouses.suppliers.store', $warehouse), $this->payload(['name' => '']))
            ->assertSessionHasErrors('name');
        $this->post(route('warehouses.suppliers.store', $warehouse), $this->payload(['email' => 'invalid']))
            ->assertSessionHasErrors('email');
        $this->post(route('warehouses.suppliers.store', $warehouse), [
            'name' => '  Proveedor Normalizado  ',
            'contact_name' => ' ',
            'phone' => '',
            'email' => ' ',
            'address' => '',
            'notes' => ' ',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('suppliers', [
            'warehouse_id' => $warehouse->id,
            'name' => 'Proveedor Normalizado',
            'contact_name' => null,
            'phone' => null,
            'email' => null,
            'address' => null,
            'notes' => null,
        ]);
    }

    public function test_name_is_unique_per_warehouse_not_globally(): void
    {
        $administrator = User::factory()->administrator()->create();
        $first = Warehouse::factory()->create();
        $second = Warehouse::factory()->create();
        Supplier::factory()->for($first)->create(['name' => 'Proveedor Compartido']);

        $this->actingAs($administrator)
            ->post(route('warehouses.suppliers.store', $first), $this->payload(['name' => 'Proveedor Compartido']))
            ->assertSessionHasErrors('name');
        $this->post(route('warehouses.suppliers.store', $second), $this->payload(['name' => 'Proveedor Compartido']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('suppliers', 2);
    }

    public function test_warehouse_id_from_request_is_ignored_on_creation(): void
    {
        $administrator = User::factory()->administrator()->create();
        $routeWarehouse = Warehouse::factory()->create();
        $injectedWarehouse = Warehouse::factory()->create();

        $this->actingAs($administrator)
            ->post(route('warehouses.suppliers.store', $routeWarehouse), $this->payload(['warehouse_id' => $injectedWarehouse->id]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('suppliers', ['warehouse_id' => $routeWarehouse->id, 'name' => 'Proveedor Médico ABC']);
        $this->assertDatabaseMissing('suppliers', ['warehouse_id' => $injectedWarehouse->id, 'name' => 'Proveedor Médico ABC']);
    }

    public function test_administrator_can_update_supplier_and_keep_its_name(): void
    {
        $administrator = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create();
        $supplier = Supplier::factory()->for($warehouse)->create(['name' => 'Proveedor Original']);

        $this->actingAs($administrator)
            ->put(route('warehouses.suppliers.update', [$warehouse, $supplier]), $this->payload([
                'name' => 'Proveedor Original',
                'contact_name' => 'Contacto Actualizado',
            ]))
            ->assertRedirect(route('warehouses.suppliers.index', $warehouse))
            ->assertSessionHas('success', 'Proveedor actualizado correctamente.');

        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'contact_name' => 'Contacto Actualizado']);
    }

    public function test_manager_can_edit_and_delete_only_in_assigned_warehouse(): void
    {
        $manager = User::factory()->warehouseManager()->create();
        $assigned = Warehouse::factory()->create();
        $other = Warehouse::factory()->create();
        $manager->warehouses()->attach($assigned);
        $assignedSupplier = Supplier::factory()->for($assigned)->create();
        $otherSupplier = Supplier::factory()->for($other)->create();

        $this->actingAs($manager)
            ->put(route('warehouses.suppliers.update', [$assigned, $assignedSupplier]), $this->payload(['name' => 'Actualizado']))
            ->assertSessionHasNoErrors();
        $this->put(route('warehouses.suppliers.update', [$other, $otherSupplier]), $this->payload())->assertForbidden();
        $this->delete(route('warehouses.suppliers.destroy', [$other, $otherSupplier]))->assertForbidden();
        $this->delete(route('warehouses.suppliers.destroy', [$assigned, $assignedSupplier]))->assertSessionHasNoErrors();

        $this->assertModelMissing($assignedSupplier);
        $this->assertModelExists($otherSupplier);
    }

    public function test_supplier_cannot_be_moved_by_a_manipulated_update(): void
    {
        $administrator = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create();
        $other = Warehouse::factory()->create();
        $supplier = Supplier::factory()->for($warehouse)->create();

        $this->actingAs($administrator)
            ->put(route('warehouses.suppliers.update', [$warehouse, $supplier]), $this->payload(['warehouse_id' => $other->id]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'warehouse_id' => $warehouse->id]);
    }

    public function test_supplier_cannot_take_duplicate_name_on_update(): void
    {
        $administrator = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create();
        $supplier = Supplier::factory()->for($warehouse)->create(['name' => 'Proveedor Uno']);
        Supplier::factory()->for($warehouse)->create(['name' => 'Proveedor Dos']);

        $this->actingAs($administrator)
            ->put(route('warehouses.suppliers.update', [$warehouse, $supplier]), $this->payload(['name' => 'Proveedor Dos']))
            ->assertSessionHasErrors('name');

        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'name' => 'Proveedor Uno']);
    }

    public function test_administrator_can_delete_supplier(): void
    {
        $administrator = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create();
        $supplier = Supplier::factory()->for($warehouse)->create();

        $this->actingAs($administrator)
            ->delete(route('warehouses.suppliers.destroy', [$warehouse, $supplier]))
            ->assertRedirect(route('warehouses.suppliers.index', $warehouse))
            ->assertSessionHas('success', 'Proveedor eliminado correctamente.');

        $this->assertModelMissing($supplier);
    }

    public function test_scoped_binding_rejects_supplier_from_another_warehouse(): void
    {
        $administrator = User::factory()->administrator()->create();
        $routeWarehouse = Warehouse::factory()->create();
        $supplier = Supplier::factory()->for(Warehouse::factory()->create())->create();

        $this->actingAs($administrator)
            ->get(route('warehouses.suppliers.edit', [$routeWarehouse, $supplier]))
            ->assertNotFound();
        $this->put(route('warehouses.suppliers.update', [$routeWarehouse, $supplier]), $this->payload())->assertNotFound();
        $this->delete(route('warehouses.suppliers.destroy', [$routeWarehouse, $supplier]))->assertNotFound();
    }

    public function test_supplier_and_warehouse_relationships_work(): void
    {
        $warehouse = Warehouse::factory()->create();
        $supplier = Supplier::factory()->for($warehouse)->create();

        $this->assertTrue($warehouse->suppliers->contains($supplier));
        $this->assertTrue($supplier->warehouse->is($warehouse));
    }

    public function test_database_prevents_deleting_warehouse_with_suppliers(): void
    {
        $warehouse = Warehouse::factory()->create();
        $supplier = Supplier::factory()->for($warehouse)->create();

        try {
            $warehouse->delete();
            $this->fail('The warehouse deletion should have been rejected by the foreign key.');
        } catch (QueryException) {
            $this->assertModelExists($warehouse);
            $this->assertModelExists($supplier);
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Proveedor Médico ABC',
            'contact_name' => 'María López',
            'phone' => '555 123 4567',
            'email' => 'contacto@example.com',
            'address' => 'Calle Principal 123',
            'notes' => 'Proveedor autorizado.',
        ], $overrides);
    }
}
