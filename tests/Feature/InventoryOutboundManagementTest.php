<?php

namespace Tests\Feature;

use App\Enums\InventoryOutboundReason;
use App\Enums\UserRole;
use App\Models\Cabinet;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryOutbound;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class InventoryOutboundManagementTest extends TestCase
{
    use RefreshDatabase;

    private int $lotSequence = 0;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_authorized_roles_are_scoped_to_their_warehouses(): void
    {
        $warehouse = Warehouse::factory()->create();
        $other = Warehouse::factory()->create();

        foreach ([UserRole::ADMINISTRATOR, UserRole::ROOT] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('warehouses.outbounds.index', $warehouse))->assertOk();
        }

        $manager = User::factory()->warehouseManager()->create();
        $manager->warehouses()->attach($warehouse);
        $this->actingAs($manager)->get(route('warehouses.outbounds.index', $warehouse))->assertOk();
        $this->get(route('warehouses.outbounds.index', $other))->assertForbidden();

        foreach ([UserRole::NURSE, UserRole::LEGACY_USER] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('warehouses.outbounds.index', $warehouse))->assertForbidden();
        }

        auth()->logout();
        $this->get(route('warehouses.outbounds.index', $warehouse))->assertRedirect(route('login'));
    }

    public function test_reason_and_items_are_required_and_reason_must_be_valid(): void
    {
        [$warehouse, $item] = $this->context();
        $user = User::factory()->administrator()->create();

        $this->actingAs($user)->post(route('warehouses.outbounds.store', $warehouse), [])
            ->assertSessionHasErrors(['reason', 'items']);
        $this->post(route('warehouses.outbounds.store', $warehouse), $this->payload($item, reason: 'nursing_voucher'))
            ->assertSessionHasErrors('reason');
        $this->assertDatabaseCount('inventory_outbounds', 0);
    }

    public function test_notes_are_trimmed_and_empty_notes_are_stored_as_null(): void
    {
        [$warehouse, $item] = $this->context();
        $this->batch($warehouse, $item, '5');
        $user = User::factory()->administrator()->create();

        $payload = $this->payload($item);
        $payload['notes'] = '  Material dañado  ';
        $this->actingAs($user)->post(route('warehouses.outbounds.store', $warehouse), $payload);
        $this->assertSame('Material dañado', InventoryOutbound::firstOrFail()->notes);

        [$otherWarehouse, $otherItem] = $this->context();
        $this->batch($otherWarehouse, $otherItem, '5');
        $payload = $this->payload($otherItem);
        $payload['notes'] = '   ';
        $this->post(route('warehouses.outbounds.store', $otherWarehouse), $payload);
        $this->assertNull(InventoryOutbound::latest('id')->firstOrFail()->notes);
    }

    public function test_only_main_inventory_items_from_route_warehouse_are_accepted(): void
    {
        [$warehouse, $item] = $this->context();
        [$otherWarehouse, $otherItem] = $this->context();
        $cabinet = Cabinet::factory()->for($warehouse)->create();
        $cabinetItem = InventoryItem::factory()->forCabinet($cabinet)->create();
        $user = User::factory()->administrator()->create();

        $this->actingAs($user)->post(route('warehouses.outbounds.store', $warehouse), $this->payload($otherItem))
            ->assertSessionHasErrors('items.0.inventory_item_id');
        $this->post(route('warehouses.outbounds.store', $warehouse), $this->payload($cabinetItem))
            ->assertSessionHasErrors('items.0.inventory_item_id');

        $payload = $this->payload($item);
        $payload['items'][] = $payload['items'][0];
        $this->post(route('warehouses.outbounds.store', $warehouse), $payload)
            ->assertSessionHasErrors('items.0.inventory_item_id');
        $this->assertDatabaseCount('inventory_outbounds', 0);
        $this->assertNotSame($warehouse->id, $otherWarehouse->id);
    }

    public function test_quantity_must_be_positive_and_insufficient_stock_rejects_outbound(): void
    {
        [$warehouse, $item] = $this->context();
        $batch = $this->batch($warehouse, $item, '4');
        $user = User::factory()->administrator()->create();

        foreach (['0', '-1'] as $quantity) {
            $this->actingAs($user)->post(route('warehouses.outbounds.store', $warehouse), $this->payload($item, $quantity))
                ->assertSessionHasErrors('items.0.quantity');
        }

        $this->post(route('warehouses.outbounds.store', $warehouse), $this->payload($item, '4.001'))
            ->assertSessionHasErrors('items.0.quantity')
            ->assertSessionHasErrors(['items.0.quantity' => 'No hay existencia utilizable suficiente para retirar este producto.']);
        $this->assertSame('4.000', $batch->fresh()->available_quantity);
        $this->assertDatabaseCount('inventory_outbounds', 0);
    }

    public function test_fefo_excludes_expired_batches_and_consumes_multiple_lots(): void
    {
        Carbon::setTestNow('2026-08-31 10:00:00');
        [$warehouse, $item] = $this->context(requiresExpiration: true);
        $expired = $this->batch($warehouse, $item, '9', '2026-08-30');
        $later = $this->batch($warehouse, $item, '20', '2026-12-01');
        $first = $this->batch($warehouse, $item, '8', '2026-10-01');

        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('warehouses.outbounds.store', $warehouse), $this->payload($item, '13.500'))
            ->assertSessionHasNoErrors();

        $allocations = InventoryOutbound::firstOrFail()->items->first()->allocations()->orderBy('id')->get();
        $this->assertSame([$first->id, $later->id], $allocations->pluck('inventory_batch_id')->all());
        $this->assertSame(['8.000', '5.500'], $allocations->pluck('quantity')->all());
        $this->assertSame('9.000', $expired->fresh()->available_quantity);
        $this->assertSame('14.500', $later->fresh()->available_quantity);
        $this->assertSame('0.000', $first->fresh()->available_quantity);
    }

    public function test_fifo_and_mixed_batches_prioritize_valid_expiration_then_oldest_undated(): void
    {
        Carbon::setTestNow('2026-08-31');
        [$warehouse, $item] = $this->context();
        $oldUndated = $this->batch($warehouse, $item, '5', null, createdAt: '2026-01-01');
        $newUndated = $this->batch($warehouse, $item, '5', null, createdAt: '2026-02-01');
        $dated = $this->batch($warehouse, $item, '3', '2026-12-01', createdAt: '2026-03-01');

        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('warehouses.outbounds.store', $warehouse), $this->payload($item, '10'))
            ->assertSessionHasNoErrors();

        $ids = InventoryOutbound::firstOrFail()->items->first()->allocations()->orderBy('id')->pluck('inventory_batch_id')->all();
        $this->assertSame([$dated->id, $oldUndated->id, $newUndated->id], $ids);
    }

    public function test_outbound_records_history_actor_source_items_allocations_and_decimal_stock(): void
    {
        Carbon::setTestNow('2026-08-31 12:30:00');
        [$warehouse, $item] = $this->context();
        $batch = $this->batch($warehouse, $item, '5.500');
        $user = User::factory()->administrator()->create();

        $this->actingAs($user)->post(route('warehouses.outbounds.store', $warehouse), $this->payload($item, '2.125'))
            ->assertRedirect();

        $outbound = InventoryOutbound::firstOrFail();
        $outboundItem = $outbound->items()->firstOrFail();
        $allocation = $outboundItem->allocations()->firstOrFail();
        $this->assertSame(InventoryOutboundReason::DAMAGE, $outbound->reason);
        $this->assertSame(InventoryOutbound::SOURCE_MANUAL, $outbound->source_type);
        $this->assertNull($outbound->source_id);
        $this->assertTrue($outbound->processedBy->is($user));
        $this->assertTrue($outbound->processed_at->equalTo(now()));
        $this->assertSame($item->id, $outboundItem->inventory_item_id);
        $this->assertSame('2.125', $outboundItem->requested_quantity);
        $this->assertSame($batch->id, $allocation->inventory_batch_id);
        $this->assertSame('2.125', $allocation->quantity);
        $this->assertSame('3.375', $batch->fresh()->available_quantity);
        $this->assertSame('5.500', $batch->fresh()->received_quantity);
    }

    public function test_failure_on_second_item_rolls_back_every_change(): void
    {
        [$warehouse, $firstItem] = $this->context();
        $secondItem = InventoryItem::factory()->forWarehouse($warehouse)->create();
        $firstBatch = $this->batch($warehouse, $firstItem, '10');
        $secondBatch = $this->batch($warehouse, $secondItem, '1');
        $payload = $this->payload($firstItem, '3');
        $payload['items'][] = ['inventory_item_id' => $secondItem->id, 'quantity' => '2'];

        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('warehouses.outbounds.store', $warehouse), $payload)
            ->assertSessionHasErrors('items.1.quantity');

        $this->assertSame('10.000', $firstBatch->fresh()->available_quantity);
        $this->assertSame('1.000', $secondBatch->fresh()->available_quantity);
        $this->assertDatabaseCount('inventory_outbounds', 0);
        $this->assertDatabaseCount('inventory_outbound_items', 0);
        $this->assertDatabaseCount('inventory_outbound_allocations', 0);
    }

    public function test_outbound_history_is_scoped_immutable_and_eager_loaded(): void
    {
        [$warehouse, $item] = $this->context();
        $this->batch($warehouse, $item, '5');
        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('warehouses.outbounds.store', $warehouse), $this->payload($item));
        $outbound = InventoryOutbound::firstOrFail();

        $this->get(route('warehouses.outbounds.show', [$warehouse, $outbound]))->assertOk();
        $this->get(route('warehouses.outbounds.show', [Warehouse::factory()->create(), $outbound]))->assertNotFound();
        $this->assertTrue(Route::has('warehouses.outbounds.index'));
        $this->assertTrue(Route::has('warehouses.outbounds.create'));
        $this->assertTrue(Route::has('warehouses.outbounds.store'));
        $this->assertTrue(Route::has('warehouses.outbounds.show'));
        $this->assertFalse(Route::has('warehouses.outbounds.edit'));
        $this->assertFalse(Route::has('warehouses.outbounds.update'));
        $this->assertFalse(Route::has('warehouses.outbounds.destroy'));
    }

    public function test_inventory_item_with_outbound_history_cannot_be_removed(): void
    {
        [$warehouse, $item] = $this->context();
        $this->batch($warehouse, $item, '5');
        $administrator = User::factory()->administrator()->create();
        $this->actingAs($administrator)
            ->post(route('warehouses.outbounds.store', $warehouse), $this->payload($item));

        $this->delete(route('warehouses.inventory.destroy', [$warehouse, $item]))
            ->assertSessionHas('error', 'No se puede retirar el producto porque tiene movimientos de inventario registrados.');
        $this->assertDatabaseHas('inventory_items', ['id' => $item->id]);
        $this->assertDatabaseCount('inventory_outbounds', 1);
    }

    private function context(bool $requiresExpiration = false): array
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['requires_expiration' => $requiresExpiration]);
        $item = InventoryItem::factory()->forWarehouse($warehouse)->for($product)->create();

        return [$warehouse, $item];
    }

    private function batch(Warehouse $warehouse, InventoryItem $item, string $quantity, ?string $expiration = null, ?string $createdAt = null): InventoryBatch
    {
        $entry = $warehouse->entries()->create([
            'supplier_id' => Supplier::factory()->for($warehouse)->create()->id,
            'invoice_number' => 'OUT-'.(++$this->lotSequence),
            'invoice_date' => today(),
            'notes' => null,
        ]);
        $entryItem = $entry->items()->create([
            'inventory_item_id' => $item->id,
            'quantity' => $quantity,
            'unit_cost' => '10.0000',
            'manufacturer_lot' => null,
            'expiration_date' => $expiration,
        ]);
        $batch = $entryItem->batch()->create([
            'inventory_item_id' => $item->id,
            'internal_lot' => sprintf('OUT-LOT-%06d', $this->lotSequence),
            'manufacturer_lot' => null,
            'expiration_date' => $expiration,
            'received_quantity' => $quantity,
            'available_quantity' => $quantity,
            'unit_cost' => '10.0000',
        ]);
        if ($createdAt) {
            $batch->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
        }

        return $batch;
    }

    private function payload(InventoryItem $item, string $quantity = '1.500', string $reason = 'damage'): array
    {
        return [
            'reason' => $reason,
            'notes' => null,
            'items' => [['inventory_item_id' => $item->id, 'quantity' => $quantity]],
        ];
    }
}
