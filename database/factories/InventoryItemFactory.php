<?php

namespace Database\Factories;

use App\Models\Cabinet;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InventoryItem> */
class InventoryItemFactory extends Factory
{
    public function definition(): array
    {
        $minimum = fake()->randomFloat(3, 0, 100);

        return [
            'stockable_type' => 'warehouse',
            'stockable_id' => Warehouse::factory(),
            'product_id' => Product::factory(),
            'location_id' => null,
            'minimum_stock' => $minimum,
            'maximum_stock' => $minimum + fake()->randomFloat(3, 1, 100),
        ];
    }

    public function forWarehouse(Warehouse $warehouse, ?Location $location = null): static
    {
        return $this->state(fn (): array => [
            'stockable_type' => $warehouse->getMorphClass(),
            'stockable_id' => $warehouse->getKey(),
            'location_id' => $location?->getKey(),
        ]);
    }

    public function forCabinet(Cabinet $cabinet): static
    {
        return $this->state(fn (): array => [
            'stockable_type' => $cabinet->getMorphClass(),
            'stockable_id' => $cabinet->getKey(),
            'location_id' => null,
        ]);
    }
}
