<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_and_root_can_access_the_category_index(): void
    {
        foreach ([UserRole::ADMINISTRATOR, UserRole::ROOT] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('categories.index'))
                ->assertOk()
                ->assertViewIs('categories.index')
                ->assertViewHas('categories');
        }
    }

    public function test_non_administrative_roles_cannot_access_the_category_index(): void
    {
        foreach ([UserRole::WAREHOUSE_MANAGER, UserRole::NURSE, UserRole::LEGACY_USER] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('categories.index'))
                ->assertForbidden();
        }
    }

    public function test_guests_are_redirected_from_all_category_routes(): void
    {
        $category = Category::factory()->create();

        $this->get(route('categories.index'))->assertRedirect(route('login'));
        $this->get(route('categories.create'))->assertRedirect(route('login'));
        $this->post(route('categories.store'))->assertRedirect(route('login'));
        $this->get(route('categories.edit', $category))->assertRedirect(route('login'));
        $this->put(route('categories.update', $category))->assertRedirect(route('login'));
        $this->delete(route('categories.destroy', $category))->assertRedirect(route('login'));
    }

    public function test_administrator_can_create_a_category(): void
    {
        $response = $this->storeCategory(UserRole::ADMINISTRATOR, [
            'name' => 'Material de Curación',
            'description' => 'Material utilizado durante procedimientos clínicos.',
        ]);

        $response->assertRedirect(route('categories.index'))
            ->assertSessionHas('success', 'Categoría creada correctamente.');
        $this->assertDatabaseHas('categories', [
            'name' => 'Material de Curación',
            'description' => 'Material utilizado durante procedimientos clínicos.',
        ]);
    }

    public function test_root_can_create_a_category(): void
    {
        $this->storeCategory(UserRole::ROOT, [
            'name' => 'Equipo Médico',
            'description' => null,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('categories', ['name' => 'Equipo Médico']);
    }

    public function test_name_is_required_and_unique(): void
    {
        Category::factory()->create(['name' => 'Medicamentos']);

        $this->storeCategory(UserRole::ADMINISTRATOR, ['name' => '', 'description' => null])
            ->assertSessionHasErrors('name');
        $this->storeCategory(UserRole::ADMINISTRATOR, ['name' => 'Medicamentos', 'description' => null])
            ->assertSessionHasErrors('name');
    }

    public function test_description_can_be_empty_and_is_stored_as_null(): void
    {
        $this->storeCategory(UserRole::ADMINISTRATOR, [
            'name' => 'Laboratorio',
            'description' => '   ',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('categories', [
            'name' => 'Laboratorio',
            'description' => null,
        ]);
    }

    public function test_outer_whitespace_is_removed_from_name_and_description(): void
    {
        $this->storeCategory(UserRole::ADMINISTRATOR, [
            'name' => '  Material Quirúrgico  ',
            'description' => '  Material para cirugía.  ',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('categories', [
            'name' => 'Material Quirúrgico',
            'description' => 'Material para cirugía.',
        ]);
        $this->assertDatabaseMissing('categories', ['name' => '  Material Quirúrgico  ']);
    }

    public function test_administrator_can_update_a_category_and_keep_its_name(): void
    {
        $administrator = User::factory()->administrator()->create();
        $category = Category::factory()->create([
            'name' => 'Medicamentos',
            'description' => 'Descripción anterior.',
        ]);

        $this->actingAs($administrator)
            ->put(route('categories.update', $category), [
                'name' => 'Medicamentos',
                'description' => 'Descripción actualizada.',
            ])
            ->assertRedirect(route('categories.index'))
            ->assertSessionHas('success', 'Categoría actualizada correctamente.')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Medicamentos',
            'description' => 'Descripción actualizada.',
        ]);
    }

    public function test_category_cannot_use_another_category_name(): void
    {
        $administrator = User::factory()->administrator()->create();
        $category = Category::factory()->create(['name' => 'Medicamentos']);
        Category::factory()->create(['name' => 'Equipo Médico']);

        $this->actingAs($administrator)
            ->put(route('categories.update', $category), [
                'name' => 'Equipo Médico',
                'description' => null,
            ])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Medicamentos']);
    }

    public function test_administrator_can_delete_a_category(): void
    {
        $administrator = User::factory()->administrator()->create();
        $category = Category::factory()->create();

        $this->actingAs($administrator)
            ->delete(route('categories.destroy', $category))
            ->assertRedirect(route('categories.index'))
            ->assertSessionHas('success', 'Categoría eliminada correctamente.');

        $this->assertModelMissing($category);
    }

    public function test_category_index_is_ordered_by_name(): void
    {
        $administrator = User::factory()->administrator()->create();
        $lastCategory = Category::factory()->create(['name' => 'Material Quirúrgico']);
        $firstCategory = Category::factory()->create(['name' => 'Equipo Médico']);

        $this->actingAs($administrator)
            ->get(route('categories.index'))
            ->assertViewHas('categories', function ($categories) use ($firstCategory, $lastCategory): bool {
                return $categories->getCollection()->pluck('id')->all() === [
                    $firstCategory->id,
                    $lastCategory->id,
                ];
            });
    }

    public function test_administrator_and_root_see_category_catalog_navigation(): void
    {
        foreach ([UserRole::ADMINISTRATOR, UserRole::ROOT] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('categories.index'))
                ->assertOk()
                ->assertSee('CATÁLOGOS')
                ->assertSee('Categorías')
                ->assertSee('Unidades')
                ->assertSee(
                    'href="'.route('categories.index').'" class="admin-nav-link active"',
                    false,
                );
        }
    }

    public function test_non_administrative_roles_do_not_see_category_navigation(): void
    {
        foreach ([UserRole::WAREHOUSE_MANAGER, UserRole::NURSE, UserRole::LEGACY_USER] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('home'))
                ->assertOk()
                ->assertDontSee(route('categories.index'), false);
        }
    }

    public function test_category_index_renders_empty_state_and_category_data(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->get(route('categories.index'))
            ->assertOk()
            ->assertSee('No hay categorías registradas.')
            ->assertSee('Crear categoría');

        $category = Category::factory()->create([
            'name' => 'Material de Curación',
            'description' => null,
        ]);

        $this->get(route('categories.index'))
            ->assertOk()
            ->assertSee('Material de Curación')
            ->assertSee('—')
            ->assertSee('¿Eliminar categoría?')
            ->assertSee(route('categories.destroy', $category), false);
    }

    public function test_category_create_and_edit_forms_render_expected_fields(): void
    {
        $administrator = User::factory()->administrator()->create();
        $category = Category::factory()->create([
            'name' => 'Equipo Médico',
            'description' => 'Equipos para atención clínica.',
        ]);

        $this->actingAs($administrator)
            ->get(route('categories.create'))
            ->assertOk()
            ->assertSee('Nueva categoría')
            ->assertSee('Nombre')
            ->assertSee('Descripción')
            ->assertSee('Guardar categoría');

        $this->get(route('categories.edit', $category))
            ->assertOk()
            ->assertSee('Editar categoría')
            ->assertSee('Equipo Médico')
            ->assertSee('Equipos para atención clínica.')
            ->assertSee('Guardar cambios');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function storeCategory(UserRole $role, array $payload): TestResponse
    {
        return $this->actingAs(User::factory()->create(['role' => $role]))
            ->post(route('categories.store'), $payload);
    }
}
