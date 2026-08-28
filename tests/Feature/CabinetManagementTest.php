<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Cabinet;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CabinetManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_and_root_can_list_cabinets_in_any_warehouse(): void
    {
        $warehouse = Warehouse::factory()->create();

        foreach ([UserRole::ADMINISTRATOR, UserRole::ROOT] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('warehouses.cabinets.index', $warehouse))
                ->assertOk()
                ->assertViewIs('cabinets.index');
        }
    }

    public function test_manager_can_list_cabinets_only_in_assigned_warehouses(): void
    {
        $manager = User::factory()->warehouseManager()->create();
        $assigned = Warehouse::factory()->create();
        $other = Warehouse::factory()->create();
        $manager->warehouses()->attach($assigned);

        $this->actingAs($manager)->get(route('warehouses.cabinets.index', $assigned))->assertOk();
        $this->get(route('warehouses.cabinets.index', $other))->assertForbidden();
    }

    public function test_nurse_and_legacy_user_cannot_list_cabinets(): void
    {
        $warehouse = Warehouse::factory()->create();

        foreach ([UserRole::NURSE, UserRole::LEGACY_USER] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $user->warehouses()->attach($warehouse);

            $this->actingAs($user)->get(route('warehouses.cabinets.index', $warehouse))->assertForbidden();
        }
    }

    public function test_guests_are_redirected_from_all_cabinet_routes(): void
    {
        $cabinet = Cabinet::factory()->create();
        $warehouse = $cabinet->warehouse;

        $this->get(route('warehouses.cabinets.index', $warehouse))->assertRedirect(route('login'));
        $this->get(route('warehouses.cabinets.create', $warehouse))->assertRedirect(route('login'));
        $this->post(route('warehouses.cabinets.store', $warehouse))->assertRedirect(route('login'));
        $this->get(route('warehouses.cabinets.edit', [$warehouse, $cabinet]))->assertRedirect(route('login'));
        $this->put(route('warehouses.cabinets.update', [$warehouse, $cabinet]))->assertRedirect(route('login'));
        $this->delete(route('warehouses.cabinets.destroy', [$warehouse, $cabinet]))->assertRedirect(route('login'));
    }

    public function test_administrator_root_and_assigned_manager_can_create_cabinets(): void
    {
        foreach ([UserRole::ADMINISTRATOR, UserRole::ROOT, UserRole::WAREHOUSE_MANAGER] as $role) {
            $warehouse = Warehouse::factory()->create();
            $user = User::factory()->create(['role' => $role]);
            if ($role === UserRole::WAREHOUSE_MANAGER) {
                $user->warehouses()->attach($warehouse);
            }

            $this->actingAs($user)
                ->post(route('warehouses.cabinets.store', $warehouse), [
                    'name' => 'Estante '.$role->value,
                    'description' => 'Área de almacenamiento.',
                ])->assertRedirect(route('warehouses.cabinets.index', $warehouse))
                ->assertSessionHas('success', 'Gabinete creado correctamente.');
        }
    }

    public function test_manager_cannot_create_in_an_unassigned_warehouse(): void
    {
        $this->actingAs(User::factory()->warehouseManager()->create())
            ->post(route('warehouses.cabinets.store', Warehouse::factory()->create()), ['name' => 'Estante A'])
            ->assertForbidden();
    }

    public function test_name_is_required_and_description_may_be_empty(): void
    {
        $warehouse = Warehouse::factory()->create();
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->post(route('warehouses.cabinets.store', $warehouse), ['name' => '', 'description' => null])
            ->assertSessionHasErrors('name');

        $this->post(route('warehouses.cabinets.store', $warehouse), ['name' => 'Refrigerador', 'description' => '   '])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('cabinets', ['warehouse_id' => $warehouse->id, 'name' => 'Refrigerador', 'description' => null]);
    }

    public function test_outer_whitespace_is_removed(): void
    {
        $warehouse = Warehouse::factory()->create();

        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('warehouses.cabinets.store', $warehouse), [
                'name' => '  Estante A  ',
                'description' => '  Material de curación.  ',
            ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('cabinets', [
            'warehouse_id' => $warehouse->id,
            'name' => 'Estante A',
            'description' => 'Material de curación.',
        ]);
    }

    public function test_name_is_unique_per_warehouse_but_reusable_in_another(): void
    {
        $first = Warehouse::factory()->create();
        $second = Warehouse::factory()->create();
        Cabinet::factory()->for($first)->create(['name' => 'Estante A']);
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->post(route('warehouses.cabinets.store', $first), ['name' => 'Estante A'])
            ->assertSessionHasErrors('name');
        $this->post(route('warehouses.cabinets.store', $second), ['name' => 'Estante A'])
            ->assertSessionHasNoErrors();
    }

    public function test_route_warehouse_controls_creation_and_request_warehouse_id_is_ignored(): void
    {
        $routeWarehouse = Warehouse::factory()->create();
        $manipulatedWarehouse = Warehouse::factory()->create();

        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('warehouses.cabinets.store', $routeWarehouse), [
                'name' => 'Gaveta 2',
                'warehouse_id' => $manipulatedWarehouse->id,
            ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('cabinets', ['warehouse_id' => $routeWarehouse->id, 'name' => 'Gaveta 2']);
        $this->assertDatabaseMissing('cabinets', ['warehouse_id' => $manipulatedWarehouse->id, 'name' => 'Gaveta 2']);
    }

    public function test_administrator_can_update_and_keep_the_same_name_without_moving_warehouse(): void
    {
        $cabinet = Cabinet::factory()->create(['name' => 'Estante A']);
        $otherWarehouse = Warehouse::factory()->create();

        $this->actingAs(User::factory()->administrator()->create())
            ->put(route('warehouses.cabinets.update', [$cabinet->warehouse, $cabinet]), [
                'name' => 'Estante A',
                'description' => 'Actualizada.',
                'warehouse_id' => $otherWarehouse->id,
            ])->assertRedirect(route('warehouses.cabinets.index', $cabinet->warehouse))
            ->assertSessionHas('success', 'Gabinete actualizado correctamente.')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('cabinets', [
            'id' => $cabinet->id,
            'warehouse_id' => $cabinet->warehouse_id,
            'description' => 'Actualizada.',
        ]);
    }

    public function test_cabinet_cannot_adopt_another_name_in_the_same_warehouse(): void
    {
        $cabinet = Cabinet::factory()->create(['name' => 'Estante A']);
        Cabinet::factory()->for($cabinet->warehouse)->create(['name' => 'Estante B']);

        $this->actingAs(User::factory()->administrator()->create())
            ->put(route('warehouses.cabinets.update', [$cabinet->warehouse, $cabinet]), ['name' => 'Estante B'])
            ->assertSessionHasErrors('name');
    }

    public function test_manager_can_update_and_delete_only_in_assigned_warehouse(): void
    {
        $manager = User::factory()->warehouseManager()->create();
        $assignedCabinet = Cabinet::factory()->create();
        $otherCabinet = Cabinet::factory()->create();
        $manager->warehouses()->attach($assignedCabinet->warehouse);

        $this->actingAs($manager)
            ->put(route('warehouses.cabinets.update', [$assignedCabinet->warehouse, $assignedCabinet]), ['name' => 'Anaquel 3'])
            ->assertSessionHasNoErrors();
        $this->put(route('warehouses.cabinets.update', [$otherCabinet->warehouse, $otherCabinet]), ['name' => 'Sin acceso'])
            ->assertForbidden();
        $this->delete(route('warehouses.cabinets.destroy', [$otherCabinet->warehouse, $otherCabinet]))
            ->assertForbidden();
        $this->delete(route('warehouses.cabinets.destroy', [$assignedCabinet->warehouse, $assignedCabinet]))
            ->assertSessionHas('success', 'Gabinete eliminado correctamente.');
    }

    public function test_administrator_can_delete_a_cabinet(): void
    {
        $cabinet = Cabinet::factory()->create();

        $this->actingAs(User::factory()->administrator()->create())
            ->delete(route('warehouses.cabinets.destroy', [$cabinet->warehouse, $cabinet]))
            ->assertRedirect(route('warehouses.cabinets.index', $cabinet->warehouse))
            ->assertSessionHas('success', 'Gabinete eliminado correctamente.');
        $this->assertModelMissing($cabinet);
    }

    public function test_scoped_binding_rejects_cabinet_from_another_warehouse(): void
    {
        $routeWarehouse = Warehouse::factory()->create();
        $cabinet = Cabinet::factory()->create();

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('warehouses.cabinets.edit', [$routeWarehouse, $cabinet]))
            ->assertNotFound();
    }

    public function test_cabinet_relationships_and_alphabetical_listing_work(): void
    {
        $warehouse = Warehouse::factory()->create();
        $last = Cabinet::factory()->for($warehouse)->create(['name' => 'Refrigerador']);
        $first = Cabinet::factory()->for($warehouse)->create(['name' => 'Anaquel 3']);

        $this->assertTrue($first->warehouse->is($warehouse));
        $this->assertCount(2, $warehouse->cabinets);
        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('warehouses.cabinets.index', $warehouse))
            ->assertViewHas('cabinets', fn ($cabinets): bool => $cabinets->getCollection()->pluck('id')->all() === [$first->id, $last->id]);
    }

    public function test_database_restricts_direct_warehouse_deletion_when_cabinets_exist(): void
    {
        $cabinet = Cabinet::factory()->create();
        $restricted = false;

        try {
            DB::table('warehouses')->where('id', $cabinet->warehouse_id)->delete();
        } catch (QueryException) {
            $restricted = true;
        }

        $this->assertTrue($restricted);
        $this->assertDatabaseHas('warehouses', ['id' => $cabinet->warehouse_id]);
        $this->assertDatabaseHas('cabinets', ['id' => $cabinet->id]);
    }
}
