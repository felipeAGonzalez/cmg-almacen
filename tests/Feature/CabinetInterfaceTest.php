<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Cabinet;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CabinetInterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_and_root_see_cabinet_action_from_warehouse_index(): void
    {
        $warehouse = Warehouse::factory()->create(['name' => 'Clínica Centro']);

        foreach ([UserRole::ADMINISTRATOR, UserRole::ROOT] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('warehouses.index'))
                ->assertOk()
                ->assertSee('Gabinetes')
                ->assertSee(route('warehouses.cabinets.index', $warehouse), false)
                ->assertSee('Proveedores');
        }
    }

    public function test_manager_navigation_lists_supplier_and_cabinet_links_only_for_assigned_warehouses(): void
    {
        $manager = User::factory()->warehouseManager()->create();
        $assigned = Warehouse::factory()->create(['name' => 'Almacén Asignado']);
        $other = Warehouse::factory()->create(['name' => 'Almacén No Asignado']);
        $manager->warehouses()->attach($assigned);

        $this->actingAs($manager)
            ->get(route('warehouses.cabinets.index', $assigned))
            ->assertOk()
            ->assertSee('MIS ALMACENES')
            ->assertSee('Almacén Asignado')
            ->assertSee('Proveedores')
            ->assertSee('Gabinetes')
            ->assertSee(route('warehouses.suppliers.index', $assigned), false)
            ->assertSee(route('warehouses.cabinets.index', $assigned), false)
            ->assertDontSee('Almacén No Asignado')
            ->assertDontSee(route('warehouses.cabinets.index', $other), false);
    }

    public function test_nurse_does_not_see_cabinet_navigation(): void
    {
        $nurse = User::factory()->nurse()->create();
        $warehouse = Warehouse::factory()->create();
        $nurse->warehouses()->attach($warehouse);

        $this->actingAs($nurse)
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee('MIS ALMACENES')
            ->assertDontSee(route('warehouses.cabinets.index', $warehouse), false);
    }

    public function test_cabinet_index_renders_context_optional_state_data_and_modal(): void
    {
        $administrator = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create(['name' => 'Clínica Centro']);

        $this->actingAs($administrator)
            ->get(route('warehouses.cabinets.index', $warehouse))
            ->assertOk()
            ->assertSee('Almacén: Clínica Centro')
            ->assertSee('Los gabinetes son puntos de resguardo independientes')
            ->assertSee('No hay gabinetes registrados en este almacén.')
            ->assertSee('El almacén puede funcionar sin gabinetes.')
            ->assertSee('Crear gabinete');

        $cabinet = Cabinet::factory()->for($warehouse)->create([
            'name' => 'Gabinete de Enfermería',
            'description' => null,
        ]);

        $this->get(route('warehouses.cabinets.index', $warehouse))
            ->assertOk()
            ->assertSee('Gabinete de Enfermería')
            ->assertSee('—')
            ->assertSee('¿Eliminar gabinete?')
            ->assertSee(route('warehouses.cabinets.destroy', [$warehouse, $cabinet]), false);
    }

    public function test_create_and_edit_forms_render_and_preload_cabinet_fields(): void
    {
        $administrator = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create(['name' => 'Clínica Centro']);
        $cabinet = Cabinet::factory()->for($warehouse)->create([
            'name' => 'Gabinete Urgencias',
            'description' => 'Material de uso frecuente.',
        ]);

        $this->actingAs($administrator)
            ->get(route('warehouses.cabinets.create', $warehouse))
            ->assertOk()
            ->assertSee('Nuevo gabinete')
            ->assertSee('Almacén: Clínica Centro')
            ->assertSee('Ej. Gabinete de Enfermería')
            ->assertSee('Guardar gabinete')
            ->assertDontSee('warehouse_id');

        $this->get(route('warehouses.cabinets.edit', [$warehouse, $cabinet]))
            ->assertOk()
            ->assertSee('Editar gabinete')
            ->assertSee('Gabinete Urgencias')
            ->assertSee('Material de uso frecuente.')
            ->assertSee('Guardar cambios');
    }
}
