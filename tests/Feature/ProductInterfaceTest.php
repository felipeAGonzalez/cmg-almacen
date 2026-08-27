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

class ProductInterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_and_root_see_active_product_navigation(): void
    {
        foreach ([UserRole::ADMINISTRATOR, UserRole::ROOT] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('products.index'))
                ->assertOk()
                ->assertSee('CATÁLOGOS')
                ->assertSee('Unidades')
                ->assertSee('Categorías')
                ->assertSee('Marcas')
                ->assertSee('Productos')
                ->assertSee('href="'.route('products.index').'" class="admin-nav-link active"', false);
        }
    }

    public function test_non_administrative_roles_do_not_see_product_navigation(): void
    {
        foreach ([UserRole::WAREHOUSE_MANAGER, UserRole::NURSE, UserRole::LEGACY_USER] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('home'))
                ->assertOk()
                ->assertDontSee(route('products.index'), false);
        }
    }

    public function test_product_index_renders_empty_state_codes_relations_and_modal(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->get(route('products.index'))
            ->assertOk()
            ->assertSee('No hay productos registrados.')
            ->assertSee('Crear producto');

        $unit = Unit::factory()->create(['name' => 'Pieza']);
        $category = Category::factory()->create(['name' => 'Material de Curación']);
        $brand = Brand::factory()->create(['name' => 'CMG Test']);
        $onlyCode = Product::factory()->create([
            'unit_id' => $unit, 'category_id' => $category, 'brand_id' => $brand,
            'name' => 'Producto interno', 'code' => 'CMG-001', 'barcode' => null,
        ]);
        Product::factory()->create([
            'unit_id' => $unit, 'category_id' => $category, 'brand_id' => $brand,
            'name' => 'Producto comercial', 'code' => null, 'barcode' => '750000000001',
        ]);
        Product::factory()->create([
            'unit_id' => $unit, 'category_id' => $category, 'brand_id' => $brand,
            'name' => 'Producto completo', 'code' => 'CMG-003', 'barcode' => '750000000003',
        ]);

        $this->get(route('products.index'))
            ->assertOk()
            ->assertSee('Interno:')
            ->assertSee('Barras:')
            ->assertSee('CMG-001')
            ->assertSee('750000000001')
            ->assertSee('Pieza')
            ->assertSee('Material de Curación')
            ->assertSee('CMG Test')
            ->assertSee('¿Eliminar producto?')
            ->assertSee(route('products.destroy', $onlyCode), false);
    }

    public function test_create_warns_when_catalogs_are_empty(): void
    {
        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('products.create'))
            ->assertOk()
            ->assertSee('Debes registrar al menos una unidad, categoría y marca antes de crear productos.')
            ->assertSee('Nombre del producto')
            ->assertSee('Código interno')
            ->assertSee('Código de barras')
            ->assertSee('Guardar producto');
    }

    public function test_edit_form_preloads_product_and_catalog_selections(): void
    {
        $product = Product::factory()->create([
            'name' => 'Jeringa desechable 10 ml',
            'code' => 'JER-10ML-001',
            'barcode' => '7501234567890',
            'description' => 'Jeringa estéril.',
        ]);

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('products.edit', $product))
            ->assertOk()
            ->assertSee('Jeringa desechable 10 ml')
            ->assertSee('JER-10ML-001')
            ->assertSee('7501234567890')
            ->assertSee('Jeringa estéril.')
            ->assertSee('value="'.$product->unit_id.'" selected', false)
            ->assertSee('value="'.$product->category_id.'" selected', false)
            ->assertSee('value="'.$product->brand_id.'" selected', false)
            ->assertSee('Guardar cambios');
    }
}
