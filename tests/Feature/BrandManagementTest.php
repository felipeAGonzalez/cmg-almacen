<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class BrandManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_and_root_can_access_the_brand_index(): void
    {
        foreach ([UserRole::ADMINISTRATOR, UserRole::ROOT] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('brands.index'))
                ->assertOk()
                ->assertViewIs('brands.index')
                ->assertViewHas('brands');
        }
    }

    public function test_non_administrative_roles_cannot_access_the_brand_index(): void
    {
        foreach ([UserRole::WAREHOUSE_MANAGER, UserRole::NURSE, UserRole::LEGACY_USER] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('brands.index'))
                ->assertForbidden();
        }
    }

    public function test_guests_are_redirected_from_all_brand_routes(): void
    {
        $brand = Brand::factory()->create();

        $this->get(route('brands.index'))->assertRedirect(route('login'));
        $this->get(route('brands.create'))->assertRedirect(route('login'));
        $this->post(route('brands.store'))->assertRedirect(route('login'));
        $this->get(route('brands.edit', $brand))->assertRedirect(route('login'));
        $this->put(route('brands.update', $brand))->assertRedirect(route('login'));
        $this->delete(route('brands.destroy', $brand))->assertRedirect(route('login'));
    }

    public function test_administrator_can_create_a_brand(): void
    {
        $this->storeBrand(UserRole::ADMINISTRATOR, [
            'name' => 'Bayer',
            'description' => 'Marca de productos médicos.',
        ])->assertRedirect(route('brands.index'))
            ->assertSessionHas('success', 'Marca creada correctamente.');

        $this->assertDatabaseHas('brands', [
            'name' => 'Bayer',
            'description' => 'Marca de productos médicos.',
        ]);
    }

    public function test_root_can_create_a_brand(): void
    {
        $this->storeBrand(UserRole::ROOT, ['name' => 'BD', 'description' => null])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('brands', ['name' => 'BD']);
    }

    public function test_name_is_required_and_unique(): void
    {
        Brand::factory()->create(['name' => 'Pisa']);

        $this->storeBrand(UserRole::ADMINISTRATOR, ['name' => '', 'description' => null])
            ->assertSessionHasErrors('name');
        $this->storeBrand(UserRole::ADMINISTRATOR, ['name' => 'Pisa', 'description' => null])
            ->assertSessionHasErrors('name');
    }

    public function test_description_can_be_empty_and_is_stored_as_null(): void
    {
        $this->storeBrand(UserRole::ADMINISTRATOR, [
            'name' => 'Genérico',
            'description' => '   ',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('brands', ['name' => 'Genérico', 'description' => null]);
    }

    public function test_outer_whitespace_is_removed_from_name_and_description(): void
    {
        $this->storeBrand(UserRole::ADMINISTRATOR, [
            'name' => '  3M  ',
            'description' => '  Insumos clínicos.  ',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('brands', ['name' => '3M', 'description' => 'Insumos clínicos.']);
        $this->assertDatabaseMissing('brands', ['name' => '  3M  ']);
    }

    public function test_administrator_can_update_a_brand_and_keep_its_name(): void
    {
        $administrator = User::factory()->administrator()->create();
        $brand = Brand::factory()->create(['name' => 'Bayer', 'description' => 'Anterior.']);

        $this->actingAs($administrator)
            ->put(route('brands.update', $brand), [
                'name' => 'Bayer',
                'description' => 'Actualizada.',
            ])
            ->assertRedirect(route('brands.index'))
            ->assertSessionHas('success', 'Marca actualizada correctamente.')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('brands', [
            'id' => $brand->id,
            'name' => 'Bayer',
            'description' => 'Actualizada.',
        ]);
    }

    public function test_brand_cannot_use_another_brand_name(): void
    {
        $administrator = User::factory()->administrator()->create();
        $brand = Brand::factory()->create(['name' => 'Bayer']);
        Brand::factory()->create(['name' => 'Pisa']);

        $this->actingAs($administrator)
            ->put(route('brands.update', $brand), ['name' => 'Pisa', 'description' => null])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseHas('brands', ['id' => $brand->id, 'name' => 'Bayer']);
    }

    public function test_administrator_can_delete_a_brand(): void
    {
        $administrator = User::factory()->administrator()->create();
        $brand = Brand::factory()->create();

        $this->actingAs($administrator)
            ->delete(route('brands.destroy', $brand))
            ->assertRedirect(route('brands.index'))
            ->assertSessionHas('success', 'Marca eliminada correctamente.');

        $this->assertModelMissing($brand);
    }

    public function test_brand_index_is_ordered_by_name(): void
    {
        $administrator = User::factory()->administrator()->create();
        $lastBrand = Brand::factory()->create(['name' => 'Pisa']);
        $firstBrand = Brand::factory()->create(['name' => 'Bayer']);

        $this->actingAs($administrator)
            ->get(route('brands.index'))
            ->assertViewHas('brands', function ($brands) use ($firstBrand, $lastBrand): bool {
                return $brands->getCollection()->pluck('id')->all() === [
                    $firstBrand->id,
                    $lastBrand->id,
                ];
            });
    }

    /** @param array<string, mixed> $payload */
    private function storeBrand(UserRole $role, array $payload): TestResponse
    {
        return $this->actingAs(User::factory()->create(['role' => $role]))
            ->post(route('brands.store'), $payload);
    }
}
