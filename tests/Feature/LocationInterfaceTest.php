<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Location;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationInterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_and_root_see_location_action_from_warehouse_index(): void
    {
        $warehouse = Warehouse::factory()->create(['name' => 'Clínica Centro']);

        foreach ([UserRole::ADMINISTRATOR, UserRole::ROOT] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('warehouses.index'))
                ->assertOk()
                ->assertSee('Ubicaciones')
                ->assertSee(route('warehouses.locations.index', $warehouse), false)
                ->assertSee('Proveedores');
        }
    }

    public function test_manager_navigation_lists_supplier_and_location_links_only_for_assigned_warehouses(): void
    {
        $manager = User::factory()->warehouseManager()->create();
        $assigned = Warehouse::factory()->create(['name' => 'Almacén Asignado']);
        $other = Warehouse::factory()->create(['name' => 'Almacén No Asignado']);
        $manager->warehouses()->attach($assigned);

        $this->actingAs($manager)
            ->get(route('warehouses.locations.index', $assigned))
            ->assertOk()
            ->assertSee('MIS ALMACENES')
            ->assertSee('Almacén Asignado')
            ->assertSee('Proveedores')
            ->assertSee('Ubicaciones')
            ->assertSee(route('warehouses.suppliers.index', $assigned), false)
            ->assertSee(route('warehouses.locations.index', $assigned), false)
            ->assertDontSee('Almacén No Asignado')
            ->assertDontSee(route('warehouses.locations.index', $other), false);
    }

    public function test_nurse_does_not_see_location_navigation(): void
    {
        $nurse = User::factory()->nurse()->create();
        $warehouse = Warehouse::factory()->create();
        $nurse->warehouses()->attach($warehouse);

        $this->actingAs($nurse)
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee('MIS ALMACENES')
            ->assertDontSee(route('warehouses.locations.index', $warehouse), false);
    }

    public function test_location_index_renders_context_optional_state_data_and_modal(): void
    {
        $administrator = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create(['name' => 'Clínica Centro']);

        $this->actingAs($administrator)
            ->get(route('warehouses.locations.index', $warehouse))
            ->assertOk()
            ->assertSee('Almacén: Clínica Centro')
            ->assertSee('Las ubicaciones son opcionales')
            ->assertSee('No hay ubicaciones registradas en este almacén.')
            ->assertSee('Puedes continuar usando el almacén sin registrar ubicaciones.')
            ->assertSee('Crear ubicación');

        $location = Location::factory()->for($warehouse)->create([
            'name' => 'Estante A',
            'description' => null,
        ]);

        $this->get(route('warehouses.locations.index', $warehouse))
            ->assertOk()
            ->assertSee('Estante A')
            ->assertSee('—')
            ->assertSee('¿Eliminar ubicación?')
            ->assertSee(route('warehouses.locations.destroy', [$warehouse, $location]), false);
    }

    public function test_create_and_edit_forms_render_and_preload_location_fields(): void
    {
        $administrator = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create(['name' => 'Clínica Centro']);
        $location = Location::factory()->for($warehouse)->create([
            'name' => 'Refrigerador',
            'description' => 'Área de medicamentos.',
        ]);

        $this->actingAs($administrator)
            ->get(route('warehouses.locations.create', $warehouse))
            ->assertOk()
            ->assertSee('Nueva ubicación')
            ->assertSee('Almacén: Clínica Centro')
            ->assertSee('Ej. Estante A')
            ->assertSee('Guardar ubicación')
            ->assertDontSee('warehouse_id');

        $this->get(route('warehouses.locations.edit', [$warehouse, $location]))
            ->assertOk()
            ->assertSee('Editar ubicación')
            ->assertSee('Refrigerador')
            ->assertSee('Área de medicamentos.')
            ->assertSee('Guardar cambios');
    }
}
