<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'category_id' => Category::factory(),
            'brand_id' => Brand::factory(),
            'name' => 'Product '.fake()->unique()->uuid(),
            'code' => 'CMG-'.fake()->unique()->uuid(),
            'barcode' => fake()->optional()->numerify('#############'),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
