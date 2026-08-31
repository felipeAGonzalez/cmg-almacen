<?php

namespace Tests\Feature;

use App\Models\Cabinet;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryStockStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_stock_totals_default_to_zero_without_batches(): void
    {
        $item = $this->warehouseItem()->newQuery()->withStockTotals()->findOrFail($this->lastItemId());

        $this->assertSame('0.000', $item->usableStock());
        $this->assertSame('0.000', $item->expiredStock());
        $this->assertSame('0.000', $item->physicalStock());
    }

    public function test_usable_expired_and_physical_stock_are_aggregated_correctly(): void
    {
        Carbon::setTestNow('2026-08-31 12:00:00');
        $item = $this->warehouseItem();
        $this->batch($item, '15.125', '2026-09-01');
        $this->batch($item, '10.250');
        $this->batch($item, '8.375', '2026-08-30');
        $this->batch($item, '0.000', '2026-09-10');

        $item = InventoryItem::query()->withStockTotals()->findOrFail($item->id);

        $this->assertSame('25.375', $item->usableStock());
        $this->assertSame('8.375', $item->expiredStock());
        $this->assertSame('33.750', $item->physicalStock());
        $this->assertSame('33.750', bcadd($item->usableStock(), $item->expiredStock(), 3));
    }

    public function test_expiration_today_and_tomorrow_are_usable_but_yesterday_is_expired(): void
    {
        Carbon::setTestNow('2026-08-31 23:59:00');
        $item = $this->warehouseItem();
        $this->batch($item, '1', '2026-08-30');
        $this->batch($item, '2', '2026-08-31');
        $this->batch($item, '3', '2026-09-01');

        $item = InventoryItem::query()->withStockTotals()->findOrFail($item->id);

        $this->assertSame('5.000', $item->usableStock());
        $this->assertSame('1.000', $item->expiredStock());
        $this->assertSame('6.000', $item->physicalStock());
    }

    public function test_stock_status_uses_usable_stock_and_exact_boundaries(): void
    {
        $this->assertStatus('0', '10', '50', InventoryItem::STOCK_STATUS_CRITICAL);
        $this->assertStatus('10', '10', '50', InventoryItem::STOCK_STATUS_CRITICAL);
        $this->assertStatus('25', '10', '50', InventoryItem::STOCK_STATUS_LOW);
        $this->assertStatus('50', '10', '50', InventoryItem::STOCK_STATUS_HEALTHY);
        $this->assertStatus('75', '10', '50', InventoryItem::STOCK_STATUS_HEALTHY);
        $this->assertStatus('0', '0', '50', InventoryItem::STOCK_STATUS_CRITICAL);
    }

    public function test_warehouse_inventory_renders_usable_expired_physical_location_and_status(): void
    {
        Carbon::setTestNow('2026-08-31 12:00:00');
        $warehouse = Warehouse::factory()->create();
        $location = Location::factory()->for($warehouse)->create(['name' => 'Estante A']);
        $item = InventoryItem::factory()->forWarehouse($warehouse, $location)->create([
            'minimum_stock' => 10,
            'maximum_stock' => 50,
        ]);
        $this->batch($item, '5.500', '2026-09-01');
        $this->batch($item, '20.000', '2026-08-30');

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('warehouses.inventory.index', $warehouse))
            ->assertOk()
            ->assertSee('Estante A')
            ->assertSee('5.5')
            ->assertSee('20 vencidas')
            ->assertSee('Total físico: 25.5')
            ->assertSee('Crítico')
            ->assertSee('text-bg-danger', false);
    }

    public function test_cabinet_inventory_uses_same_status_without_location_column(): void
    {
        $warehouse = Warehouse::factory()->create();
        $cabinet = Cabinet::factory()->for($warehouse)->create();
        $item = InventoryItem::factory()->forCabinet($cabinet)->create([
            'minimum_stock' => 10,
            'maximum_stock' => 50,
        ]);
        $this->batch($item, '25');

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('warehouses.cabinets.inventory.index', [$warehouse, $cabinet]))
            ->assertOk()
            ->assertSee('25')
            ->assertSee('Bajo')
            ->assertSee('text-bg-warning', false)
            ->assertDontSee('<th scope="col">Ubicación</th>', false);
    }

    public function test_inventory_aggregate_query_count_does_not_grow_per_item(): void
    {
        $warehouse = Warehouse::factory()->create();
        InventoryItem::factory()->count(15)->forWarehouse($warehouse)->create();
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $items = $warehouse->inventoryItems()
            ->withStockTotals()
            ->with(['product.unit', 'location'])
            ->get();

        $this->assertCount(15, $items);
        $this->assertLessThanOrEqual(4, $queries);
        $this->assertTrue($items->every(fn (InventoryItem $item) => array_key_exists('usable_stock', $item->getAttributes())));
    }

    private function assertStatus(string $stock, string $minimum, string $maximum, string $expected): void
    {
        $item = $this->warehouseItem($minimum, $maximum);
        if (bccomp($stock, '0', 3) > 0) {
            $this->batch($item, $stock);
        }
        $item = InventoryItem::query()->withStockTotals()->findOrFail($item->id);

        $this->assertSame($expected, $item->stockStatus());
    }

    private function warehouseItem(string $minimum = '10', string $maximum = '50'): InventoryItem
    {
        $warehouse = Warehouse::factory()->create();

        return InventoryItem::factory()->forWarehouse($warehouse)->create([
            'minimum_stock' => $minimum,
            'maximum_stock' => $maximum,
        ]);
    }

    private function lastItemId(): int
    {
        return InventoryItem::query()->latest('id')->value('id');
    }

    private function batch(InventoryItem $item, string $quantity, ?string $expirationDate = null): InventoryBatch
    {
        return InventoryBatch::query()->create([
            'inventory_item_id' => $item->id,
            'entry_item_id' => null,
            'source_batch_id' => null,
            'internal_lot' => 'TEST-'.uniqid(),
            'manufacturer_lot' => null,
            'expiration_date' => $expirationDate,
            'received_quantity' => $quantity,
            'available_quantity' => $quantity,
            'unit_cost' => '1.0000',
        ]);
    }
}
