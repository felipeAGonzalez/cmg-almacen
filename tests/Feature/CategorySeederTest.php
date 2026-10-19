<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategorySeederTest extends TestCase
{
    use RefreshDatabase;

    private const CATEGORIES = [
        'medications' => 'Medicamentos',
        'medical_supplies' => 'Insumo médico',
        'healing_material' => 'Material para curación',
        'consumables' => 'Consumible',
        'medical_devices' => 'Dispositivo médico',
    ];

    public function test_seeder_creates_the_initial_categories(): void
    {
        $this->seed(CategorySeeder::class);

        foreach (self::CATEGORIES as $key => $name) {
            $this->assertDatabaseHas('categories', compact('key', 'name'));
        }
    }

    public function test_seeder_is_idempotent_and_preserves_existing_categories(): void
    {
        $existing = Category::factory()->create(['name' => 'Categoría existente']);
        Category::factory()->create([
            'key' => Product::MEDICATION_CATEGORY_KEY,
            'name' => 'Medicamentos',
            'description' => 'Descripción personalizada',
        ]);

        $this->seed(CategorySeeder::class);
        $this->seed(CategorySeeder::class);

        $this->assertModelExists($existing);
        $this->assertSame(1, Category::query()->where('key', Product::MEDICATION_CATEGORY_KEY)->count());
        $this->assertSame('Descripción personalizada', Category::query()->where('key', Product::MEDICATION_CATEGORY_KEY)->value('description'));
        $this->assertSame(6, Category::query()->count());
    }

    public function test_seeded_categories_are_available_to_products(): void
    {
        $this->seed(CategorySeeder::class);
        $category = Category::query()->where('name', 'Insumo médico')->firstOrFail();
        $product = Product::factory()->for($category)->create();

        $this->assertTrue($product->category->is($category));
    }
}
