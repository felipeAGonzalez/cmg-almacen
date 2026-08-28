<?php

namespace Database\Factories;

use App\Models\Cabinet;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Cabinet> */
class CabinetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'warehouse_id' => Warehouse::factory(),
            'name' => 'Cabinet '.fake()->unique()->uuid(),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
