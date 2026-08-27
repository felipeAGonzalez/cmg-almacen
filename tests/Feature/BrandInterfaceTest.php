<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandInterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_and_root_see_active_brand_navigation(): void
    {
        foreach ([UserRole::ADMINISTRATOR, UserRole::ROOT] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('brands.index'))
                ->assertOk()
                ->assertSee('CATÁLOGOS')
                ->assertSee('Unidades')
                ->assertSee('Categorías')
                ->assertSee('Marcas')
                ->assertSee('href="'.route('brands.index').'" class="admin-nav-link active"', false);
        }
    }

    public function test_non_administrative_roles_do_not_see_brand_navigation(): void
    {
        foreach ([UserRole::WAREHOUSE_MANAGER, UserRole::NURSE, UserRole::LEGACY_USER] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('home'))
                ->assertOk()
                ->assertDontSee(route('brands.index'), false);
        }
    }

    public function test_brand_index_renders_empty_state_and_brand_data(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->get(route('brands.index'))
            ->assertOk()
            ->assertSee('No hay marcas registradas.')
            ->assertSee('Crear marca');

        $brand = Brand::factory()->create(['name' => '3M', 'description' => null]);

        $this->get(route('brands.index'))
            ->assertOk()
            ->assertSee('3M')
            ->assertSee('—')
            ->assertSee('¿Eliminar marca?')
            ->assertSee(route('brands.destroy', $brand), false);
    }

    public function test_brand_create_and_edit_forms_render_expected_fields(): void
    {
        $administrator = User::factory()->administrator()->create();
        $brand = Brand::factory()->create([
            'name' => 'Bayer',
            'description' => 'Productos para atención clínica.',
        ]);

        $this->actingAs($administrator)
            ->get(route('brands.create'))
            ->assertOk()
            ->assertSee('Nueva marca')
            ->assertSee('Nombre')
            ->assertSee('Descripción')
            ->assertSee('Guardar marca');

        $this->get(route('brands.edit', $brand))
            ->assertOk()
            ->assertSee('Editar marca')
            ->assertSee('Bayer')
            ->assertSee('Productos para atención clínica.')
            ->assertSee('Guardar cambios');
    }
}
