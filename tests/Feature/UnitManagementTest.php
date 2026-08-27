<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class UnitManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_and_root_can_access_the_unit_index(): void
    {
        foreach ([UserRole::ADMINISTRATOR, UserRole::ROOT] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('units.index'))
                ->assertOk()
                ->assertViewIs('units.index')
                ->assertViewHas('units');
        }
    }

    public function test_non_administrative_roles_cannot_access_the_unit_index(): void
    {
        foreach ([UserRole::WAREHOUSE_MANAGER, UserRole::NURSE, UserRole::LEGACY_USER] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('units.index'))
                ->assertForbidden();
        }
    }

    public function test_guests_are_redirected_from_all_unit_routes(): void
    {
        $unit = Unit::factory()->create();

        $this->get(route('units.index'))->assertRedirect(route('login'));
        $this->get(route('units.create'))->assertRedirect(route('login'));
        $this->post(route('units.store'))->assertRedirect(route('login'));
        $this->get(route('units.edit', $unit))->assertRedirect(route('login'));
        $this->put(route('units.update', $unit))->assertRedirect(route('login'));
        $this->delete(route('units.destroy', $unit))->assertRedirect(route('login'));
    }

    public function test_administrator_can_create_a_unit(): void
    {
        $response = $this->storeUnit(UserRole::ADMINISTRATOR, [
            'name' => 'Pieza',
            'abbreviation' => 'PZ',
        ]);

        $response->assertRedirect(route('units.index'))
            ->assertSessionHas('success', 'Unidad creada correctamente.');
        $this->assertDatabaseHas('units', ['name' => 'Pieza', 'abbreviation' => 'PZ']);
    }

    public function test_root_can_create_a_unit(): void
    {
        $this->storeUnit(UserRole::ROOT, ['name' => 'Caja', 'abbreviation' => 'CJ'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('units', ['name' => 'Caja', 'abbreviation' => 'CJ']);
    }

    public function test_name_is_required_and_unique(): void
    {
        Unit::factory()->create(['name' => 'Pieza']);

        $this->storeUnit(UserRole::ADMINISTRATOR, ['name' => '', 'abbreviation' => null])
            ->assertSessionHasErrors('name');
        $this->storeUnit(UserRole::ADMINISTRATOR, ['name' => 'Pieza', 'abbreviation' => null])
            ->assertSessionHasErrors('name');
    }

    public function test_abbreviation_is_optional_and_multiple_units_can_have_null(): void
    {
        $this->storeUnit(UserRole::ADMINISTRATOR, ['name' => 'Pieza', 'abbreviation' => ''])
            ->assertSessionHasNoErrors();
        $this->storeUnit(UserRole::ADMINISTRATOR, ['name' => 'Caja', 'abbreviation' => null])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('units', ['name' => 'Pieza', 'abbreviation' => null]);
        $this->assertDatabaseHas('units', ['name' => 'Caja', 'abbreviation' => null]);
    }

    public function test_abbreviation_must_be_unique_when_present(): void
    {
        Unit::factory()->create(['name' => 'Pieza', 'abbreviation' => 'PZ']);

        $this->storeUnit(UserRole::ADMINISTRATOR, ['name' => 'Paquete', 'abbreviation' => 'PZ'])
            ->assertSessionHasErrors('abbreviation');
    }

    public function test_outer_whitespace_is_removed_from_name_and_abbreviation(): void
    {
        $this->storeUnit(UserRole::ADMINISTRATOR, [
            'name' => '  Mililitro  ',
            'abbreviation' => '  ML  ',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('units', ['name' => 'Mililitro', 'abbreviation' => 'ML']);
        $this->assertDatabaseMissing('units', ['name' => '  Mililitro  ']);
        $this->assertDatabaseMissing('units', ['abbreviation' => '  ML  ']);
    }

    public function test_administrator_can_update_a_unit_and_keep_its_unique_values(): void
    {
        $administrator = User::factory()->administrator()->create();
        $unit = Unit::factory()->create(['name' => 'Pieza', 'abbreviation' => 'PZ']);

        $this->actingAs($administrator)
            ->put(route('units.update', $unit), [
                'name' => 'Pieza',
                'abbreviation' => 'PZ',
            ])
            ->assertRedirect(route('units.index'))
            ->assertSessionHas('success', 'Unidad actualizada correctamente.')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('units', ['id' => $unit->id, 'name' => 'Pieza', 'abbreviation' => 'PZ']);
    }

    public function test_administrator_can_edit_a_unit_values(): void
    {
        $administrator = User::factory()->administrator()->create();
        $unit = Unit::factory()->create(['name' => 'Pieza', 'abbreviation' => 'PZ']);

        $this->actingAs($administrator)
            ->put(route('units.update', $unit), [
                'name' => 'Paquete',
                'abbreviation' => 'PQ',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('units', ['id' => $unit->id, 'name' => 'Paquete', 'abbreviation' => 'PQ']);
    }

    public function test_unit_cannot_use_another_unit_name_or_abbreviation(): void
    {
        $administrator = User::factory()->administrator()->create();
        $unit = Unit::factory()->create(['name' => 'Pieza', 'abbreviation' => 'PZ']);
        Unit::factory()->create(['name' => 'Caja', 'abbreviation' => 'CJ']);

        $this->actingAs($administrator)
            ->put(route('units.update', $unit), ['name' => 'Caja', 'abbreviation' => 'PZ'])
            ->assertSessionHasErrors('name');
        $this->put(route('units.update', $unit), ['name' => 'Pieza', 'abbreviation' => 'CJ'])
            ->assertSessionHasErrors('abbreviation');

        $this->assertDatabaseHas('units', ['id' => $unit->id, 'name' => 'Pieza', 'abbreviation' => 'PZ']);
    }

    public function test_administrator_can_delete_a_unit(): void
    {
        $administrator = User::factory()->administrator()->create();
        $unit = Unit::factory()->create();

        $this->actingAs($administrator)
            ->delete(route('units.destroy', $unit))
            ->assertRedirect(route('units.index'))
            ->assertSessionHas('success', 'Unidad eliminada correctamente.');

        $this->assertModelMissing($unit);
    }

    public function test_unit_index_is_ordered_by_name(): void
    {
        $administrator = User::factory()->administrator()->create();
        $lastUnit = Unit::factory()->create(['name' => 'Rollo']);
        $firstUnit = Unit::factory()->create(['name' => 'Caja']);

        $this->actingAs($administrator)
            ->get(route('units.index'))
            ->assertViewHas('units', function ($units) use ($firstUnit, $lastUnit): bool {
                return $units->getCollection()->pluck('id')->all() === [$firstUnit->id, $lastUnit->id];
            });
    }

    public function test_administrator_and_root_see_unit_catalog_navigation(): void
    {
        foreach ([UserRole::ADMINISTRATOR, UserRole::ROOT] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('units.index'))
                ->assertOk()
                ->assertSee('CATÁLOGOS')
                ->assertSee('Unidades')
                ->assertSee(
                    'href="'.route('units.index').'" class="admin-nav-link active"',
                    false,
                );
        }
    }

    public function test_non_administrative_roles_do_not_see_unit_navigation(): void
    {
        foreach ([UserRole::WAREHOUSE_MANAGER, UserRole::NURSE, UserRole::LEGACY_USER] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('home'))
                ->assertOk()
                ->assertDontSee(route('units.index'), false);
        }
    }

    public function test_unit_index_renders_empty_state_and_unit_data(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->get(route('units.index'))
            ->assertOk()
            ->assertSee('No hay unidades registradas.')
            ->assertSee('Crear unidad');

        $unit = Unit::factory()->create(['name' => 'Pieza', 'abbreviation' => null]);

        $this->get(route('units.index'))
            ->assertOk()
            ->assertSee('Pieza')
            ->assertSee('—')
            ->assertSee('¿Eliminar unidad?')
            ->assertSee(route('units.destroy', $unit), false);
    }

    public function test_unit_create_and_edit_forms_render_expected_fields(): void
    {
        $administrator = User::factory()->administrator()->create();
        $unit = Unit::factory()->create(['name' => 'Mililitro', 'abbreviation' => 'ML']);

        $this->actingAs($administrator)
            ->get(route('units.create'))
            ->assertOk()
            ->assertSee('Nueva unidad')
            ->assertSee('Nombre')
            ->assertSee('Abreviatura')
            ->assertSee('Guardar unidad');

        $this->get(route('units.edit', $unit))
            ->assertOk()
            ->assertSee('Editar unidad')
            ->assertSee('Mililitro')
            ->assertSee('ML')
            ->assertSee('Guardar cambios');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function storeUnit(UserRole $role, array $payload): TestResponse
    {
        return $this->actingAs(User::factory()->create(['role' => $role]))
            ->post(route('units.store'), $payload);
    }
}
