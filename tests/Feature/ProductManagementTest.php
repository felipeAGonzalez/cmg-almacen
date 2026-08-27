<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_and_root_can_access_product_index(): void
    {
        foreach ([UserRole::ADMINISTRATOR, UserRole::ROOT] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('products.index'))
                ->assertOk()
                ->assertViewIs('products.index');
        }
    }

    public function test_non_administrative_roles_cannot_access_product_index(): void
    {
        foreach ([UserRole::WAREHOUSE_MANAGER, UserRole::NURSE, UserRole::LEGACY_USER] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('products.index'))
                ->assertForbidden();
        }
    }

    public function test_guests_are_redirected_from_all_product_routes(): void
    {
        $product = Product::factory()->create();

        $this->get(route('products.index'))->assertRedirect(route('login'));
        $this->get(route('products.create'))->assertRedirect(route('login'));
        $this->post(route('products.store'))->assertRedirect(route('login'));
        $this->get(route('products.edit', $product))->assertRedirect(route('login'));
        $this->put(route('products.update', $product))->assertRedirect(route('login'));
        $this->delete(route('products.destroy', $product))->assertRedirect(route('login'));
    }

    public function test_products_can_be_created_with_code_barcode_or_both(): void
    {
        $administrator = User::factory()->administrator()->create();

        foreach ([
            ['name' => 'Con código', 'code' => 'CMG-001', 'barcode' => null],
            ['name' => 'Con barras', 'code' => null, 'barcode' => '750000000001'],
            ['name' => 'Con ambos', 'code' => 'CMG-003', 'barcode' => '750000000003'],
        ] as $identification) {
            $this->actingAs($administrator)
                ->post(route('products.store'), $this->payload($identification))
                ->assertRedirect(route('products.index'))
                ->assertSessionHas('success', 'Producto creado correctamente.')
                ->assertSessionHasNoErrors();
        }

        $this->assertDatabaseCount('products', 3);
    }

    public function test_root_can_create_a_product(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::ROOT]))
            ->post(route('products.store'), $this->payload())
            ->assertSessionHasNoErrors();
    }

    public function test_name_and_catalogs_are_required(): void
    {
        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('products.store'), [
                'name' => '', 'code' => 'CMG-001', 'barcode' => null,
                'unit_id' => null, 'category_id' => null, 'brand_id' => null,
            ])->assertSessionHasErrors(['name', 'unit_id', 'category_id', 'brand_id']);
    }

    public function test_at_least_one_identification_code_is_required(): void
    {
        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('products.store'), $this->payload(['code' => '', 'barcode' => '']))
            ->assertSessionHasErrors(['code', 'barcode']);
    }

    public function test_catalog_ids_must_exist(): void
    {
        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('products.store'), $this->payload([
                'unit_id' => 999999, 'category_id' => 999999, 'brand_id' => 999999,
            ]))->assertSessionHasErrors(['unit_id', 'category_id', 'brand_id']);
    }

    public function test_code_and_barcode_are_globally_unique(): void
    {
        Product::factory()->create(['code' => 'CMG-EXISTING', 'barcode' => '750000000099']);
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->post(route('products.store'), $this->payload(['code' => 'CMG-EXISTING', 'barcode' => '750000000100']))
            ->assertSessionHasErrors('code');
        $this->post(route('products.store'), $this->payload(['code' => 'CMG-NEW', 'barcode' => '750000000099']))
            ->assertSessionHasErrors('barcode');
    }

    public function test_optional_strings_are_normalized_and_outer_whitespace_is_trimmed(): void
    {
        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('products.store'), $this->payload([
                'name' => '  Guantes clínicos  ',
                'code' => '  CMG-GUA-01  ',
                'barcode' => '   ',
                'description' => '   ',
            ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('products', [
            'name' => 'Guantes clínicos', 'code' => 'CMG-GUA-01',
            'barcode' => null, 'description' => null,
        ]);
    }

    public function test_product_and_inverse_catalog_relationships_work(): void
    {
        $unit = Unit::factory()->create();
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();
        $products = Product::factory()->count(2)->create([
            'unit_id' => $unit, 'category_id' => $category, 'brand_id' => $brand,
        ]);

        $this->assertTrue($products->first()->unit->is($unit));
        $this->assertTrue($products->first()->category->is($category));
        $this->assertTrue($products->first()->brand->is($brand));
        $this->assertCount(2, $unit->products);
        $this->assertCount(2, $category->products);
        $this->assertCount(2, $brand->products);
    }

    public function test_administrator_can_update_and_keep_current_identifiers(): void
    {
        $product = Product::factory()->create(['code' => 'CMG-KEEP', 'barcode' => '750000000010']);

        $this->actingAs(User::factory()->administrator()->create())
            ->put(route('products.update', $product), $this->payload([
                'name' => 'Producto actualizado', 'code' => 'CMG-KEEP', 'barcode' => '750000000010',
            ]))->assertRedirect(route('products.index'))
            ->assertSessionHas('success', 'Producto actualizado correctamente.')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Producto actualizado']);
    }

    public function test_product_cannot_use_another_products_identifiers(): void
    {
        $product = Product::factory()->create(['code' => 'CMG-ONE', 'barcode' => '750000000011']);
        Product::factory()->create(['code' => 'CMG-TWO', 'barcode' => '750000000012']);
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->put(route('products.update', $product), $this->payload(['code' => 'CMG-TWO', 'barcode' => '750000000011']))
            ->assertSessionHasErrors('code');
        $this->put(route('products.update', $product), $this->payload(['code' => 'CMG-ONE', 'barcode' => '750000000012']))
            ->assertSessionHasErrors('barcode');
    }

    public function test_product_can_switch_between_code_and_barcode_but_cannot_clear_both(): void
    {
        $administrator = User::factory()->administrator()->create();
        $product = Product::factory()->create(['code' => 'CMG-SWITCH', 'barcode' => null]);

        $this->actingAs($administrator)
            ->put(route('products.update', $product), $this->payload(['code' => '', 'barcode' => '750000000020']))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'code' => null, 'barcode' => '750000000020']);

        $this->put(route('products.update', $product), $this->payload(['code' => 'CMG-RETURN', 'barcode' => '']))
            ->assertSessionHasNoErrors();
        $this->put(route('products.update', $product), $this->payload(['code' => '', 'barcode' => '']))
            ->assertSessionHasErrors(['code', 'barcode']);
    }

    public function test_administrator_can_delete_a_product(): void
    {
        $product = Product::factory()->create();

        $this->actingAs(User::factory()->administrator()->create())
            ->delete(route('products.destroy', $product))
            ->assertRedirect(route('products.index'))
            ->assertSessionHas('success', 'Producto eliminado correctamente.');

        $this->assertModelMissing($product);
    }

    public function test_index_is_ordered_and_eager_loads_catalog_relations(): void
    {
        $last = Product::factory()->create(['name' => 'Venda']);
        $first = Product::factory()->create(['name' => 'Aguja']);

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('products.index'))
            ->assertViewHas('products', function ($products) use ($first, $last): bool {
                $items = $products->getCollection();

                return $items->pluck('id')->all() === [$first->id, $last->id]
                    && $items->every(fn (Product $product): bool => $product->relationLoaded('unit')
                        && $product->relationLoaded('category') && $product->relationLoaded('brand'));
            });
    }

    public function test_create_and_edit_receive_alphabetically_ordered_catalogs(): void
    {
        Unit::factory()->create(['name' => 'Pieza']);
        Unit::factory()->create(['name' => 'Caja']);
        Category::factory()->create(['name' => 'Quirúrgico']);
        Category::factory()->create(['name' => 'Medicamentos']);
        Brand::factory()->create(['name' => 'Pisa']);
        Brand::factory()->create(['name' => 'Bayer']);
        $administrator = User::factory()->administrator()->create();

        foreach ([route('products.create'), route('products.edit', Product::factory()->create())] as $url) {
            $this->actingAs($administrator)->get($url)
                ->assertOk()
                ->assertViewHas('units', fn ($items): bool => $items->pluck('name')->values()->all() === $items->pluck('name')->sort()->values()->all())
                ->assertViewHas('categories', fn ($items): bool => $items->pluck('name')->values()->all() === $items->pluck('name')->sort()->values()->all())
                ->assertViewHas('brands', fn ($items): bool => $items->pluck('name')->values()->all() === $items->pluck('name')->sort()->values()->all());
        }
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Producto de prueba',
            'code' => 'CMG-'.fake()->unique()->uuid(),
            'barcode' => null,
            'unit_id' => Unit::factory()->create()->id,
            'category_id' => Category::factory()->create()->id,
            'brand_id' => Brand::factory()->create()->id,
            'description' => 'Descripción de prueba.',
        ], $overrides);
    }
}
