<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductCatalogProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogs_used_by_products_cannot_be_deleted_through_controllers(): void
    {
        $administrator = User::factory()->administrator()->create();
        $product = Product::factory()->create();

        foreach ([
            [route('units.destroy', $product->unit), 'units', $product->unit_id, 'No se puede eliminar la unidad porque está siendo utilizada por productos.'],
            [route('categories.destroy', $product->category), 'categories', $product->category_id, 'No se puede eliminar la categoría porque está siendo utilizada por productos.'],
            [route('brands.destroy', $product->brand), 'brands', $product->brand_id, 'No se puede eliminar la marca porque está siendo utilizada por productos.'],
        ] as [$url, $table, $id, $message]) {
            $this->actingAs($administrator)->delete($url)
                ->assertSessionHas('error', $message);

            $this->assertDatabaseHas($table, ['id' => $id]);
            $this->assertDatabaseHas('products', ['id' => $product->id]);
        }
    }

    public function test_unused_catalogs_can_still_be_deleted(): void
    {
        $administrator = User::factory()->administrator()->create();
        $unit = Unit::factory()->create();
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();

        foreach ([
            [route('units.destroy', $unit), $unit],
            [route('categories.destroy', $category), $category],
            [route('brands.destroy', $brand), $brand],
        ] as [$url, $model]) {
            $this->actingAs($administrator)->delete($url)->assertSessionHasNoErrors();
            $this->assertModelMissing($model);
        }
    }

    public function test_database_foreign_keys_restrict_direct_catalog_deletion(): void
    {
        foreach (['unit_id' => 'units', 'category_id' => 'categories', 'brand_id' => 'brands'] as $foreignKey => $table) {
            $product = Product::factory()->create();
            $restricted = false;

            try {
                DB::table($table)->where('id', $product->{$foreignKey})->delete();
            } catch (QueryException) {
                $restricted = true;
            }

            $this->assertTrue($restricted, "The {$table} foreign key did not restrict deletion.");
            $this->assertDatabaseHas($table, ['id' => $product->{$foreignKey}]);
            $this->assertDatabaseHas('products', ['id' => $product->id]);
        }
    }
}
