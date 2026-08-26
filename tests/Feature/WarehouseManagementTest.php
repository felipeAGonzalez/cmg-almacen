<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class WarehouseManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_administrator_can_access_the_warehouse_index(): void
    {
        $response = $this->actingAs(User::factory()->administrator()->create())
            ->get(route('warehouses.index'));

        $response->assertOk()->assertViewIs('warehouses.index');
    }

    public function test_root_can_access_the_warehouse_index(): void
    {
        $response = $this->actingAs(User::factory()->create(['role' => UserRole::ROOT]))
            ->get(route('warehouses.index'));

        $response->assertOk();
    }

    public function test_non_administrative_roles_cannot_access_the_warehouse_index(): void
    {
        foreach ([UserRole::WAREHOUSE_MANAGER, UserRole::NURSE, UserRole::LEGACY_USER] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get(route('warehouses.index'))->assertForbidden();
        }
    }

    public function test_guests_are_redirected_from_all_warehouse_routes(): void
    {
        $warehouse = Warehouse::factory()->create();

        $this->get(route('warehouses.index'))->assertRedirect(route('login'));
        $this->get(route('warehouses.create'))->assertRedirect(route('login'));
        $this->post(route('warehouses.store'))->assertRedirect(route('login'));
        $this->get(route('warehouses.edit', $warehouse))->assertRedirect(route('login'));
        $this->put(route('warehouses.update', $warehouse))->assertRedirect(route('login'));
        $this->delete(route('warehouses.destroy', $warehouse))->assertRedirect(route('login'));
    }

    public function test_an_administrator_can_create_a_warehouse(): void
    {
        $response = $this->storeWarehouse(UserRole::ADMINISTRATOR, 'Almacén Central');

        $response->assertRedirect(route('warehouses.index'))
            ->assertSessionHas('success', 'Almacén creado correctamente.');
        $this->assertDatabaseHas('warehouses', ['name' => 'Almacén Central']);
    }

    public function test_root_can_create_a_warehouse(): void
    {
        $response = $this->storeWarehouse(UserRole::ROOT, 'Almacén Técnico');

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('warehouses', ['name' => 'Almacén Técnico']);
    }

    public function test_warehouse_name_is_required(): void
    {
        $this->storeWarehouse(UserRole::ADMINISTRATOR, '')
            ->assertSessionHasErrors('name');
    }

    public function test_warehouse_name_must_be_unique(): void
    {
        Warehouse::factory()->create(['name' => 'Almacén Central']);

        $this->storeWarehouse(UserRole::ADMINISTRATOR, 'Almacén Central')
            ->assertSessionHasErrors('name');
    }

    public function test_outer_whitespace_is_removed_from_the_warehouse_name(): void
    {
        $this->storeWarehouse(UserRole::ADMINISTRATOR, '  Almacén Centro  ')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('warehouses', ['name' => 'Almacén Centro']);
        $this->assertDatabaseMissing('warehouses', ['name' => '  Almacén Centro  ']);
    }

    public function test_an_administrator_can_update_a_warehouse(): void
    {
        $administrator = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create(['name' => 'Almacén Anterior']);

        $response = $this->actingAs($administrator)
            ->put(route('warehouses.update', $warehouse), ['name' => 'Almacén Actualizado']);

        $response->assertRedirect(route('warehouses.index'))
            ->assertSessionHas('success', 'Almacén actualizado correctamente.');
        $this->assertDatabaseHas('warehouses', ['id' => $warehouse->id, 'name' => 'Almacén Actualizado']);
    }

    public function test_a_warehouse_can_keep_its_current_name_when_updated(): void
    {
        $administrator = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create(['name' => 'Almacén Central']);

        $this->actingAs($administrator)
            ->put(route('warehouses.update', $warehouse), ['name' => 'Almacén Central'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('warehouses', ['id' => $warehouse->id, 'name' => 'Almacén Central']);
    }

    public function test_a_warehouse_cannot_use_another_warehouse_name(): void
    {
        $administrator = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create(['name' => 'Almacén Norte']);
        Warehouse::factory()->create(['name' => 'Almacén Sur']);

        $this->actingAs($administrator)
            ->put(route('warehouses.update', $warehouse), ['name' => 'Almacén Sur'])
            ->assertSessionHasErrors('name');

        $this->assertSame('Almacén Norte', $warehouse->fresh()->name);
    }

    public function test_a_warehouse_without_users_can_be_deleted(): void
    {
        $administrator = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create();

        $response = $this->actingAs($administrator)->delete(route('warehouses.destroy', $warehouse));

        $response->assertRedirect(route('warehouses.index'))
            ->assertSessionHas('success', 'Almacén eliminado correctamente.');
        $this->assertModelMissing($warehouse);
    }

    public function test_a_warehouse_with_assigned_users_cannot_be_deleted(): void
    {
        $administrator = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create();
        $warehouse->users()->attach(User::factory()->create());

        $response = $this->actingAs($administrator)->delete(route('warehouses.destroy', $warehouse));

        $response->assertRedirect(route('warehouses.index'))
            ->assertSessionHas('error', 'No se puede eliminar el almacén porque tiene usuarios asignados.');
        $this->assertModelExists($warehouse);
    }

    public function test_a_warehouse_with_suppliers_cannot_be_deleted(): void
    {
        $administrator = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create();
        $supplier = Supplier::factory()->for($warehouse)->create();

        $response = $this->actingAs($administrator)
            ->delete(route('warehouses.destroy', $warehouse));

        $response
            ->assertRedirect(route('warehouses.index'))
            ->assertSessionHas(
                'error',
                'No se puede eliminar el almacén porque tiene proveedores registrados.',
            );
        $this->assertModelExists($warehouse);
        $this->assertModelExists($supplier);
    }

    public function test_the_index_is_alphabetical_and_includes_the_user_count(): void
    {
        $administrator = User::factory()->administrator()->create();
        $lastWarehouse = Warehouse::factory()->create(['name' => 'Zeta']);
        $firstWarehouse = Warehouse::factory()->create(['name' => 'Almacén Central']);
        $firstWarehouse->users()->attach(User::factory()->count(2)->create());

        $response = $this->actingAs($administrator)->get(route('warehouses.index'));

        $response->assertViewHas('warehouses', function ($warehouses) use ($firstWarehouse, $lastWarehouse): bool {
            $items = $warehouses->getCollection();

            return $items->pluck('id')->all() === [$firstWarehouse->id, $lastWarehouse->id]
                && $items->firstWhere('id', $firstWarehouse->id)->users_count === 2;
        });
    }

    private function storeWarehouse(UserRole $role, string $name): TestResponse
    {
        $user = User::factory()->create(['role' => $role]);

        return $this->actingAs($user)->post(route('warehouses.store'), ['name' => $name]);
    }
}
