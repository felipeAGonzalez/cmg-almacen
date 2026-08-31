<?php

namespace Tests\Feature;

use App\Enums\InventoryOutboundReason;
use App\Enums\UserRole;
use App\Models\Cabinet;
use App\Models\InventoryItem;
use App\Models\InventoryOutbound;
use App\Models\InventoryTransfer;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryKardexService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryKardexTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_warehouse_kardex_projects_entries_transfers_and_outbounds_per_batch(): void
    {
        Carbon::setTestNow('2026-08-31 12:00:00');
        [$warehouse, $cabinet, $source, , $user] = $this->context();
        [$entry, $batch] = $this->entryBatch($warehouse, $source, '20', 'MFG-01', '2027-03-15');
        $transfer = $this->transfer($warehouse, $cabinet, $source, $user, '5');
        $outbound = $this->outbound($warehouse, $source, $user, '3');

        $movements = app(InventoryKardexService::class)->forWarehouse($warehouse, []);
        $this->assertSame(3, $movements->total());
        $this->assertSame(['manual_outbound', 'transfer_out', 'entry'], $movements->pluck('movement_type')->all());
        $this->assertSame(['3', '5', '20'], $movements->pluck('formatted_quantity')->all());
        $this->assertSame([$batch->internal_lot], $movements->pluck('internal_lot')->unique()->values()->all());

        $this->actingAs($user)->get(route('warehouses.kardex.index', $warehouse))->assertOk()
            ->assertSee('Entrada')->assertSee('Transferencia enviada')->assertSee('Salida manual')
            ->assertSee('MFG-01')->assertSee('Caduca: 15/03/2027')->assertSee($user->name)
            ->assertSee(route('warehouses.entries.show', [$warehouse, $entry]), false)
            ->assertSee(route('warehouses.transfers.show', [$warehouse, $transfer]), false)
            ->assertSee(route('warehouses.outbounds.show', [$warehouse, $outbound]), false);
    }

    public function test_multiple_allocations_are_individual_movements_without_duplicates(): void
    {
        [$warehouse, $cabinet, $source, , $user] = $this->context();
        $this->entryBatch($warehouse, $source, '4', internalLot: 'ROOT-A');
        $this->entryBatch($warehouse, $source, '6', internalLot: 'ROOT-B');
        $this->transfer($warehouse, $cabinet, $source, $user, '7');

        $rows = app(InventoryKardexService::class)->forWarehouse($warehouse, ['movement_type' => 'transfer_out']);
        $this->assertCount(2, $rows);
        $this->assertSame(['ROOT-A', 'ROOT-B'], $rows->pluck('internal_lot')->sort()->values()->all());
        $this->assertSame('7.000', $rows->reduce(fn (string $sum, $row) => bcadd($sum, $row->quantity, 3), '0.000'));
    }

    public function test_cabinet_kardex_contains_only_transfer_in_with_derived_and_source_lots(): void
    {
        [$warehouse, $cabinet, $source, $destination, $user] = $this->context();
        [, $rootBatch] = $this->entryBatch($warehouse, $source, '8', 'FAB-88');
        $transfer = $this->transfer($warehouse, $cabinet, $source, $user, '3.500');
        $allocation = $transfer->items()->firstOrFail()->allocations()->with('destinationBatch')->firstOrFail();

        $movements = app(InventoryKardexService::class)->forCabinet($warehouse, $cabinet, []);
        $this->assertSame(1, $movements->total());
        $movement = $movements->first();
        $this->assertSame('transfer_in', $movement->movement_type);
        $this->assertSame($destination->id, $movement->inventory_item_id);
        $this->assertSame($allocation->destinationBatch->internal_lot, $movement->internal_lot);
        $this->assertSame($rootBatch->internal_lot, $movement->source_internal_lot);

        $this->actingAs($user)->get(route('warehouses.cabinets.kardex.index', [$warehouse, $cabinet]))
            ->assertOk()->assertSee('Transferencia recibida')->assertSee($allocation->destinationBatch->internal_lot)
            ->assertSee('Origen: '.$rootBatch->internal_lot)->assertDontSee('Salida manual')->assertDontSee('Proveedor:');
    }

    public function test_context_isolation_and_manipulated_inventory_item_are_rejected(): void
    {
        [$warehouse, $cabinet, $source, , $user] = $this->context();
        [$otherWarehouse, $otherCabinet, $otherSource] = $this->context();
        $this->entryBatch($warehouse, $source, '2');
        $this->entryBatch($otherWarehouse, $otherSource, '9');

        $this->actingAs($user)->get(route('warehouses.kardex.index', $warehouse))->assertOk()
            ->assertSee($source->product->name)->assertDontSee($otherSource->product->name);
        $this->get(route('warehouses.kardex.index', [$warehouse, 'inventory_item_id' => $otherSource->id]))
            ->assertRedirect()->assertSessionHasErrors('inventory_item_id');
        $this->get(route('warehouses.cabinets.kardex.index', [$warehouse, $otherCabinet]))->assertNotFound();
        $this->assertNotSame($cabinet->id, $otherCabinet->id);
    }

    public function test_product_type_and_date_filters_can_be_combined_and_cleared(): void
    {
        [$warehouse, , $first, , $user] = $this->context();
        $second = InventoryItem::factory()->forWarehouse($warehouse)->create();
        $this->entryBatch($warehouse, $first, '2', createdAt: '2026-08-01 10:00:00');
        $this->entryBatch($warehouse, $second, '3', createdAt: '2026-08-20 10:00:00');

        $service = app(InventoryKardexService::class);
        $this->assertSame(2, $service->forWarehouse($warehouse, [])->total());
        $this->assertSame(1, $service->forWarehouse($warehouse, ['inventory_item_id' => $first->id])->total());
        $this->assertSame(2, $service->forWarehouse($warehouse, ['movement_type' => 'entry'])->total());
        $this->assertSame(1, $service->forWarehouse($warehouse, ['date_from' => '2026-08-10'])->total());
        $this->assertSame(1, $service->forWarehouse($warehouse, ['date_to' => '2026-08-10'])->total());
        $this->assertSame(1, $service->forWarehouse($warehouse, [
            'inventory_item_id' => $second->id, 'movement_type' => 'entry',
            'date_from' => '2026-08-15', 'date_to' => '2026-08-25',
        ])->total());

        $this->actingAs($user)->get(route('warehouses.kardex.index', [$warehouse, 'date_from' => '2026-08-20', 'date_to' => '2026-08-01']))
            ->assertRedirect()->assertSessionHasErrors('date_to');
    }

    public function test_authorization_matches_warehouse_management_rules(): void
    {
        $warehouse = Warehouse::factory()->create();
        foreach ([UserRole::ADMINISTRATOR, UserRole::ROOT] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('warehouses.kardex.index', $warehouse))->assertOk();
        }
        $manager = User::factory()->warehouseManager()->create();
        $manager->warehouses()->attach($warehouse);
        $this->actingAs($manager)->get(route('warehouses.kardex.index', $warehouse))->assertOk();
        $this->get(route('warehouses.kardex.index', Warehouse::factory()->create()))->assertForbidden();
        foreach ([UserRole::NURSE, UserRole::LEGACY_USER] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('warehouses.kardex.index', $warehouse))->assertForbidden();
        }
        auth()->logout();
        $this->get(route('warehouses.kardex.index', $warehouse))->assertRedirect(route('login'));
    }

    public function test_navigation_is_contextual_and_hidden_from_nurse(): void
    {
        $warehouse = Warehouse::factory()->create();
        $cabinet = Cabinet::factory()->for($warehouse)->create();
        $administrator = User::factory()->administrator()->create();
        $this->actingAs($administrator)->get(route('warehouses.index'))->assertSee(route('warehouses.kardex.index', $warehouse), false);
        $this->get(route('warehouses.cabinets.index', $warehouse))->assertSee(route('warehouses.cabinets.kardex.index', [$warehouse, $cabinet]), false);
        $manager = User::factory()->warehouseManager()->create();
        $manager->warehouses()->attach($warehouse);
        $this->actingAs($manager)->get(route('home'))->assertSee(route('warehouses.kardex.index', $warehouse), false);
        $this->actingAs(User::factory()->create(['role' => UserRole::NURSE]))->get(route('home'))->assertDontSee('Kardex');
    }

    public function test_movements_are_stably_ordered_paginated_and_query_count_is_constant(): void
    {
        [$warehouse, , $item, , $user] = $this->context();
        foreach (range(1, 26) as $day) {
            $this->entryBatch($warehouse, $item, '1', createdAt: sprintf('2026-08-%02d 10:00:00', $day));
        }
        $service = app(InventoryKardexService::class);
        $page = $service->forWarehouse($warehouse, []);
        $this->assertSame(26, $page->total());
        $this->assertCount(25, $page);
        $this->assertSame('2026-08-26', $page->first()->occurred_at->format('Y-m-d'));

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->get(route('warehouses.kardex.index', $warehouse))->assertOk();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThan(12, $queryCount);
    }

    private function context(): array
    {
        $warehouse = Warehouse::factory()->create();
        $cabinet = Cabinet::factory()->for($warehouse)->create();
        $product = Product::factory()->create();
        $source = InventoryItem::factory()->forWarehouse($warehouse)->for($product)->create();
        $destination = InventoryItem::factory()->forCabinet($cabinet)->for($product)->create();

        return [$warehouse, $cabinet, $source, $destination, User::factory()->administrator()->create()];
    }

    private function entryBatch(Warehouse $warehouse, InventoryItem $item, string $quantity, ?string $manufacturerLot = null, ?string $expiration = null, ?string $internalLot = null, ?string $createdAt = null): array
    {
        $entry = $warehouse->entries()->create([
            'supplier_id' => Supplier::factory()->for($warehouse)->create()->id,
            'invoice_number' => 'KDX-'.(++$this->sequence), 'invoice_date' => today(),
        ]);
        if ($createdAt) {
            $entry->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
        }
        $entryItem = $entry->items()->create([
            'inventory_item_id' => $item->id, 'quantity' => $quantity, 'unit_cost' => '10.0000',
            'manufacturer_lot' => $manufacturerLot, 'expiration_date' => $expiration,
        ]);
        $batch = $entryItem->batch()->create([
            'inventory_item_id' => $item->id, 'internal_lot' => $internalLot ?: 'KDX-LOT-'.$this->sequence,
            'manufacturer_lot' => $manufacturerLot, 'expiration_date' => $expiration,
            'received_quantity' => $quantity, 'available_quantity' => $quantity, 'unit_cost' => '10.0000',
        ]);

        return [$entry, $batch];
    }

    private function transfer(Warehouse $warehouse, Cabinet $cabinet, InventoryItem $item, User $user, string $quantity): InventoryTransfer
    {
        Carbon::setTestNow(now()->addMinute());
        $this->actingAs($user)->post(route('warehouses.transfers.store', $warehouse), [
            'cabinet_id' => $cabinet->id, 'notes' => null,
            'items' => [['source_inventory_item_id' => $item->id, 'quantity' => $quantity]],
        ])->assertSessionHasNoErrors();

        return InventoryTransfer::query()->latest('id')->firstOrFail();
    }

    private function outbound(Warehouse $warehouse, InventoryItem $item, User $user, string $quantity): InventoryOutbound
    {
        Carbon::setTestNow(now()->addMinute());
        $this->actingAs($user)->post(route('warehouses.outbounds.store', $warehouse), [
            'reason' => InventoryOutboundReason::DAMAGE->value, 'notes' => null,
            'items' => [['inventory_item_id' => $item->id, 'quantity' => $quantity]],
        ])->assertSessionHasNoErrors();

        return InventoryOutbound::query()->latest('id')->firstOrFail();
    }
}
