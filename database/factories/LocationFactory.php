<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Location> */
class LocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'warehouse_id' => Warehouse::factory(),
            'name' => 'Location '.fake()->unique()->uuid(),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
