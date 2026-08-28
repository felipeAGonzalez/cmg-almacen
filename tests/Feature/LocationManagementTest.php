<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Location;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LocationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_and_root_can_list_locations_in_any_warehouse(): void
    {
        $warehouse = Warehouse::factory()->create();

        foreach ([UserRole::ADMINISTRATOR, UserRole::ROOT] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('warehouses.locations.index', $warehouse))
                ->assertOk()
                ->assertViewIs('locations.index');
        }
    }

    public function test_manager_can_list_locations_only_in_assigned_warehouses(): void
    {
        $manager = User::factory()->warehouseManager()->create();
        $assigned = Warehouse::factory()->create();
        $other = Warehouse::factory()->create();
        $manager->warehouses()->attach($assigned);

        $this->actingAs($manager)->get(route('warehouses.locations.index', $assigned))->assertOk();
        $this->get(route('warehouses.locations.index', $other))->assertForbidden();
    }

    public function test_nurse_and_legacy_user_cannot_list_locations(): void
    {
        $warehouse = Warehouse::factory()->create();

        foreach ([UserRole::NURSE, UserRole::LEGACY_USER] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $user->warehouses()->attach($warehouse);

            $this->actingAs($user)->get(route('warehouses.locations.index', $warehouse))->assertForbidden();
        }
    }

    public function test_guests_are_redirected_from_all_location_routes(): void
    {
        $location = Location::factory()->create();
        $warehouse = $location->warehouse;

        $this->get(route('warehouses.locations.index', $warehouse))->assertRedirect(route('login'));
        $this->get(route('warehouses.locations.create', $warehouse))->assertRedirect(route('login'));
        $this->post(route('warehouses.locations.store', $warehouse))->assertRedirect(route('login'));
        $this->get(route('warehouses.locations.edit', [$warehouse, $location]))->assertRedirect(route('login'));
        $this->put(route('warehouses.locations.update', [$warehouse, $location]))->assertRedirect(route('login'));
        $this->delete(route('warehouses.locations.destroy', [$warehouse, $location]))->assertRedirect(route('login'));
    }

    public function test_administrator_root_and_assigned_manager_can_create_locations(): void
    {
        foreach ([UserRole::ADMINISTRATOR, UserRole::ROOT, UserRole::WAREHOUSE_MANAGER] as $role) {
            $warehouse = Warehouse::factory()->create();
            $user = User::factory()->create(['role' => $role]);
            if ($role === UserRole::WAREHOUSE_MANAGER) {
                $user->warehouses()->attach($warehouse);
            }

            $this->actingAs($user)
                ->post(route('warehouses.locations.store', $warehouse), [
                    'name' => 'Estante '.$role->value,
                    'description' => 'Área de almacenamiento.',
                ])->assertRedirect(route('warehouses.locations.index', $warehouse))
                ->assertSessionHas('success', 'Ubicación creada correctamente.');
        }
    }

    public function test_manager_cannot_create_in_an_unassigned_warehouse(): void
    {
        $this->actingAs(User::factory()->warehouseManager()->create())
            ->post(route('warehouses.locations.store', Warehouse::factory()->create()), ['name' => 'Estante A'])
            ->assertForbidden();
    }

    public function test_name_is_required_and_description_may_be_empty(): void
    {
        $warehouse = Warehouse::factory()->create();
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->post(route('warehouses.locations.store', $warehouse), ['name' => '', 'description' => null])
            ->assertSessionHasErrors('name');

        $this->post(route('warehouses.locations.store', $warehouse), ['name' => 'Refrigerador', 'description' => '   '])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('locations', ['warehouse_id' => $warehouse->id, 'name' => 'Refrigerador', 'description' => null]);
    }

    public function test_outer_whitespace_is_removed(): void
    {
        $warehouse = Warehouse::factory()->create();

        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('warehouses.locations.store', $warehouse), [
                'name' => '  Estante A  ',
                'description' => '  Material de curación.  ',
            ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('locations', [
            'warehouse_id' => $warehouse->id,
            'name' => 'Estante A',
            'description' => 'Material de curación.',
        ]);
    }

    public function test_name_is_unique_per_warehouse_but_reusable_in_another(): void
    {
        $first = Warehouse::factory()->create();
        $second = Warehouse::factory()->create();
        Location::factory()->for($first)->create(['name' => 'Estante A']);
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->post(route('warehouses.locations.store', $first), ['name' => 'Estante A'])
            ->assertSessionHasErrors('name');
        $this->post(route('warehouses.locations.store', $second), ['name' => 'Estante A'])
            ->assertSessionHasNoErrors();
    }

    public function test_route_warehouse_controls_creation_and_request_warehouse_id_is_ignored(): void
    {
        $routeWarehouse = Warehouse::factory()->create();
        $manipulatedWarehouse = Warehouse::factory()->create();

        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('warehouses.locations.store', $routeWarehouse), [
                'name' => 'Gaveta 2',
                'warehouse_id' => $manipulatedWarehouse->id,
            ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('locations', ['warehouse_id' => $routeWarehouse->id, 'name' => 'Gaveta 2']);
        $this->assertDatabaseMissing('locations', ['warehouse_id' => $manipulatedWarehouse->id, 'name' => 'Gaveta 2']);
    }

    public function test_administrator_can_update_and_keep_the_same_name_without_moving_warehouse(): void
    {
        $location = Location::factory()->create(['name' => 'Estante A']);
        $otherWarehouse = Warehouse::factory()->create();

        $this->actingAs(User::factory()->administrator()->create())
            ->put(route('warehouses.locations.update', [$location->warehouse, $location]), [
                'name' => 'Estante A',
                'description' => 'Actualizada.',
                'warehouse_id' => $otherWarehouse->id,
            ])->assertRedirect(route('warehouses.locations.index', $location->warehouse))
            ->assertSessionHas('success', 'Ubicación actualizada correctamente.')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'warehouse_id' => $location->warehouse_id,
            'description' => 'Actualizada.',
        ]);
    }

    public function test_location_cannot_adopt_another_name_in_the_same_warehouse(): void
    {
        $location = Location::factory()->create(['name' => 'Estante A']);
        Location::factory()->for($location->warehouse)->create(['name' => 'Estante B']);

        $this->actingAs(User::factory()->administrator()->create())
            ->put(route('warehouses.locations.update', [$location->warehouse, $location]), ['name' => 'Estante B'])
            ->assertSessionHasErrors('name');
    }

    public function test_manager_can_update_and_delete_only_in_assigned_warehouse(): void
    {
        $manager = User::factory()->warehouseManager()->create();
        $assignedLocation = Location::factory()->create();
        $otherLocation = Location::factory()->create();
        $manager->warehouses()->attach($assignedLocation->warehouse);

        $this->actingAs($manager)
            ->put(route('warehouses.locations.update', [$assignedLocation->warehouse, $assignedLocation]), ['name' => 'Anaquel 3'])
            ->assertSessionHasNoErrors();
        $this->put(route('warehouses.locations.update', [$otherLocation->warehouse, $otherLocation]), ['name' => 'Sin acceso'])
            ->assertForbidden();
        $this->delete(route('warehouses.locations.destroy', [$otherLocation->warehouse, $otherLocation]))
            ->assertForbidden();
        $this->delete(route('warehouses.locations.destroy', [$assignedLocation->warehouse, $assignedLocation]))
            ->assertSessionHas('success', 'Ubicación eliminada correctamente.');
    }

    public function test_administrator_can_delete_a_location(): void
    {
        $location = Location::factory()->create();

        $this->actingAs(User::factory()->administrator()->create())
            ->delete(route('warehouses.locations.destroy', [$location->warehouse, $location]))
            ->assertRedirect(route('warehouses.locations.index', $location->warehouse))
            ->assertSessionHas('success', 'Ubicación eliminada correctamente.');
        $this->assertModelMissing($location);
    }

    public function test_scoped_binding_rejects_location_from_another_warehouse(): void
    {
        $routeWarehouse = Warehouse::factory()->create();
        $location = Location::factory()->create();

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('warehouses.locations.edit', [$routeWarehouse, $location]))
            ->assertNotFound();
    }

    public function test_location_relationships_and_alphabetical_listing_work(): void
    {
        $warehouse = Warehouse::factory()->create();
        $last = Location::factory()->for($warehouse)->create(['name' => 'Refrigerador']);
        $first = Location::factory()->for($warehouse)->create(['name' => 'Anaquel 3']);

        $this->assertTrue($first->warehouse->is($warehouse));
        $this->assertCount(2, $warehouse->locations);
        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('warehouses.locations.index', $warehouse))
            ->assertViewHas('locations', fn ($locations): bool => $locations->getCollection()->pluck('id')->all() === [$first->id, $last->id]);
    }

    public function test_database_restricts_direct_warehouse_deletion_when_locations_exist(): void
    {
        $location = Location::factory()->create();
        $restricted = false;

        try {
            DB::table('warehouses')->where('id', $location->warehouse_id)->delete();
        } catch (QueryException) {
            $restricted = true;
        }

        $this->assertTrue($restricted);
        $this->assertDatabaseHas('warehouses', ['id' => $location->warehouse_id]);
        $this->assertDatabaseHas('locations', ['id' => $location->id]);
    }
}
