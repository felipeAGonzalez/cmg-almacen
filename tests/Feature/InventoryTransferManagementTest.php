<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Cabinet;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryTransfer;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class InventoryTransferManagementTest extends TestCase
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
                ->get(route('warehouses.transfers.index', $warehouse))->assertOk();
        }

        $manager = User::factory()->warehouseManager()->create();
        $manager->warehouses()->attach($warehouse);
        $this->actingAs($manager)->get(route('warehouses.transfers.index', $warehouse))->assertOk();
        $this->get(route('warehouses.transfers.index', $other))->assertForbidden();

        foreach ([UserRole::NURSE, UserRole::LEGACY_USER] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('warehouses.transfers.index', $warehouse))->assertForbidden();
        }

        auth()->logout();
        $this->get(route('warehouses.transfers.index', $warehouse))->assertRedirect(route('login'));
    }

    public function test_cabinet_and_source_inventory_must_belong_to_route_warehouse(): void
    {
        [$warehouse, $cabinet, $source] = $this->context();
        $otherWarehouse = Warehouse::factory()->create();
        $otherCabinet = Cabinet::factory()->for($otherWarehouse)->create();
        $otherSource = InventoryItem::factory()->forWarehouse($otherWarehouse)->create();
        $cabinetSource = InventoryItem::factory()->forCabinet($cabinet)->create();
        $user = User::factory()->administrator()->create();

        $this->actingAs($user)->post(route('warehouses.transfers.store', $warehouse), $this->payload($otherCabinet, $source))
            ->assertSessionHasErrors('cabinet_id');
        $this->post(route('warehouses.transfers.store', $warehouse), $this->payload($cabinet, $otherSource))
            ->assertSessionHasErrors('items.0.source_inventory_item_id');
        $this->post(route('warehouses.transfers.store', $warehouse), $this->payload($cabinet, $cabinetSource))
            ->assertSessionHasErrors('items.0.source_inventory_item_id');
        $this->assertDatabaseCount('inventory_transfers', 0);
    }

    public function test_destination_configuration_is_required_and_matches_product(): void
    {
        [$warehouse, $cabinet, $source] = $this->context(configureDestination: false);
        $this->batch($warehouse, $source, '5');

        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('warehouses.transfers.store', $warehouse), $this->payload($cabinet, $source))
            ->assertSessionHasErrors('items.0.source_inventory_item_id');

        $this->assertDatabaseCount('inventory_transfers', 0);
    }

    public function test_quantity_rules_and_duplicate_sources_are_rejected(): void
    {
        [$warehouse, $cabinet, $source] = $this->context();
        $user = User::factory()->administrator()->create();

        foreach (['0', '-1'] as $quantity) {
            $this->actingAs($user)->post(route('warehouses.transfers.store', $warehouse), $this->payload($cabinet, $source, $quantity))
                ->assertSessionHasErrors('items.0.quantity');
        }

        $payload = $this->payload($cabinet, $source);
        $payload['items'][] = $payload['items'][0];
        $this->post(route('warehouses.transfers.store', $warehouse), $payload)
            ->assertSessionHasErrors('items.0.source_inventory_item_id');
    }

    public function test_fefo_excludes_empty_and_expired_batches_and_consumes_multiple_lots(): void
    {
        Carbon::setTestNow('2026-08-28 10:00:00');
        [$warehouse, $cabinet, $source] = $this->context(requiresExpiration: true);
        $expired = $this->batch($warehouse, $source, '9', '2026-08-27');
        $empty = $this->batch($warehouse, $source, '0', '2026-09-01');
        $later = $this->batch($warehouse, $source, '20', '2026-12-01');
        $first = $this->batch($warehouse, $source, '10', '2026-10-01');

        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('warehouses.transfers.store', $warehouse), $this->payload($cabinet, $source, '25.000'))
            ->assertSessionHasNoErrors();

        $transfer = InventoryTransfer::firstOrFail();
        $allocations = $transfer->items->first()->allocations()->orderBy('id')->get();
        $this->assertSame([$first->id, $later->id], $allocations->pluck('source_batch_id')->all());
        $this->assertSame(['10.000', '15.000'], $allocations->pluck('quantity')->all());
        $this->assertSame('9.000', $expired->fresh()->available_quantity);
        $this->assertSame('0.000', $empty->fresh()->available_quantity);
        $this->assertSame('5.000', $later->fresh()->available_quantity);
        $this->assertSame('0.000', $first->fresh()->available_quantity);
    }

    public function test_fifo_and_mixed_batches_use_expiring_stock_then_oldest_undated_stock(): void
    {
        Carbon::setTestNow('2026-08-28');
        [$warehouse, $cabinet, $source] = $this->context();
        $oldUndated = $this->batch($warehouse, $source, '5', null, createdAt: '2026-01-01');
        $newUndated = $this->batch($warehouse, $source, '5', null, createdAt: '2026-02-01');
        $dated = $this->batch($warehouse, $source, '3', '2026-12-01', createdAt: '2026-03-01');

        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('warehouses.transfers.store', $warehouse), $this->payload($cabinet, $source, '10'))
            ->assertSessionHasNoErrors();

        $ids = InventoryTransfer::firstOrFail()->items->first()->allocations()->orderBy('id')->pluck('source_batch_id')->all();
        $this->assertSame([$dated->id, $oldUndated->id, $newUndated->id], $ids);
    }

    public function test_insufficient_usable_stock_rejects_the_complete_transfer(): void
    {
        Carbon::setTestNow('2026-08-28');
        [$warehouse, $cabinet, $source] = $this->context();
        $batch = $this->batch($warehouse, $source, '4');

        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('warehouses.transfers.store', $warehouse), $this->payload($cabinet, $source, '4.001'))
            ->assertSessionHasErrors('items.0.quantity');

        $this->assertSame('4.000', $batch->fresh()->available_quantity);
        $this->assertDatabaseCount('inventory_transfers', 0);
        $this->assertDatabaseCount('inventory_transfer_allocations', 0);
    }

    public function test_transfer_records_actor_items_allocations_and_traceable_destination_batch(): void
    {
        Carbon::setTestNow('2026-08-28 12:30:00');
        [$warehouse, $cabinet, $source, $destination] = $this->context(requiresExpiration: true);
        $root = $this->batch($warehouse, $source, '5.500', '2027-01-15', 'FAB-100', '8.1250');
        $user = User::factory()->administrator()->create();

        $this->actingAs($user)->post(route('warehouses.transfers.store', $warehouse), $this->payload($cabinet, $source, '2.500'))
            ->assertRedirect();

        $transfer = InventoryTransfer::firstOrFail();
        $item = $transfer->items()->firstOrFail();
        $allocation = $item->allocations()->firstOrFail();
        $derived = $allocation->destinationBatch;

        $this->assertTrue($transfer->warehouse->is($warehouse));
        $this->assertTrue($transfer->cabinet->is($cabinet));
        $this->assertTrue($transfer->transferredBy->is($user));
        $this->assertTrue($transfer->transferred_at->equalTo(now()));
        $this->assertSame($source->id, $item->source_inventory_item_id);
        $this->assertSame($destination->id, $item->destination_inventory_item_id);
        $this->assertSame($source->product_id, $destination->product_id);
        $this->assertSame('3.000', $root->fresh()->available_quantity);
        $this->assertSame($root->id, $derived->source_batch_id);
        $this->assertNull($derived->entry_item_id);
        $this->assertSame('FAB-100', $derived->manufacturer_lot);
        $this->assertSame('2027-01-15', $derived->expiration_date->toDateString());
        $this->assertSame('8.1250', $derived->unit_cost);
        $this->assertSame('2.500', $derived->received_quantity);
        $this->assertSame('2.500', $derived->available_quantity);
        $this->assertNotSame($root->internal_lot, $derived->internal_lot);
        $this->assertDatabaseCount('inventory_transfer_allocations', 1);
    }

    public function test_existing_destination_batch_accumulates_received_and_available_quantities(): void
    {
        [$warehouse, $cabinet, $source] = $this->context();
        $root = $this->batch($warehouse, $source, '10');
        $user = User::factory()->administrator()->create();

        $this->actingAs($user)->post(route('warehouses.transfers.store', $warehouse), $this->payload($cabinet, $source, '2'));
        $this->post(route('warehouses.transfers.store', $warehouse), $this->payload($cabinet, $source, '3'));

        $derived = InventoryBatch::where('source_batch_id', $root->id)->firstOrFail();
        $this->assertSame('5.000', $derived->received_quantity);
        $this->assertSame('5.000', $derived->available_quantity);
        $this->assertDatabaseCount('inventory_batches', 2);
        $this->assertDatabaseCount('inventory_transfer_allocations', 2);
    }

    public function test_failure_on_second_product_rolls_back_every_change(): void
    {
        [$warehouse, $cabinet, $firstSource] = $this->context();
        $secondProduct = Product::factory()->create();
        $secondSource = InventoryItem::factory()->forWarehouse($warehouse)->for($secondProduct)->create();
        InventoryItem::factory()->forCabinet($cabinet)->for($secondProduct)->create();
        $firstBatch = $this->batch($warehouse, $firstSource, '10');
        $secondBatch = $this->batch($warehouse, $secondSource, '1');
        $payload = $this->payload($cabinet, $firstSource, '3');
        $payload['items'][] = ['source_inventory_item_id' => $secondSource->id, 'quantity' => '2'];

        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('warehouses.transfers.store', $warehouse), $payload)
            ->assertSessionHasErrors('items.1.quantity');

        $this->assertSame('10.000', $firstBatch->fresh()->available_quantity);
        $this->assertSame('1.000', $secondBatch->fresh()->available_quantity);
        $this->assertDatabaseCount('inventory_transfers', 0);
        $this->assertDatabaseCount('inventory_transfer_items', 0);
        $this->assertDatabaseCount('inventory_transfer_allocations', 0);
        $this->assertDatabaseCount('inventory_batches', 2);
    }

    public function test_scoped_binding_rejects_transfer_from_another_warehouse(): void
    {
        [$warehouse, $cabinet, $source] = $this->context();
        $this->batch($warehouse, $source, '5');
        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('warehouses.transfers.store', $warehouse), $this->payload($cabinet, $source));
        $transfer = InventoryTransfer::firstOrFail();

        $this->get(route('warehouses.transfers.show', [Warehouse::factory()->create(), $transfer]))
            ->assertNotFound();
    }

    public function test_cabinet_and_inventory_items_with_transfer_history_cannot_be_removed(): void
    {
        [$warehouse, $cabinet, $source, $destination] = $this->context();
        $this->batch($warehouse, $source, '5');
        $administrator = User::factory()->administrator()->create();
        $this->actingAs($administrator)
            ->post(route('warehouses.transfers.store', $warehouse), $this->payload($cabinet, $source));

        $this->delete(route('warehouses.inventory.destroy', [$warehouse, $source]))
            ->assertSessionHas('error', 'No se puede retirar el producto porque tiene movimientos de inventario registrados.');
        $this->delete(route('warehouses.cabinets.inventory.destroy', [$warehouse, $cabinet, $destination]))
            ->assertSessionHas('error', 'No se puede retirar el producto porque tiene movimientos de inventario registrados.');
        $this->delete(route('warehouses.cabinets.destroy', [$warehouse, $cabinet]))
            ->assertSessionHas('error', 'No se puede eliminar el gabinete porque tiene transferencias registradas.');

        $this->assertDatabaseHas('inventory_items', ['id' => $source->id]);
        $this->assertDatabaseHas('inventory_items', ['id' => $destination->id]);
        $this->assertDatabaseHas('cabinets', ['id' => $cabinet->id]);
        $this->assertDatabaseCount('inventory_transfers', 1);
    }

    public function test_transfer_history_has_only_index_create_store_and_show_routes(): void
    {
        $this->assertTrue(Route::has('warehouses.transfers.index'));
        $this->assertTrue(Route::has('warehouses.transfers.create'));
        $this->assertTrue(Route::has('warehouses.transfers.store'));
        $this->assertTrue(Route::has('warehouses.transfers.show'));
        $this->assertFalse(Route::has('warehouses.transfers.edit'));
        $this->assertFalse(Route::has('warehouses.transfers.update'));
        $this->assertFalse(Route::has('warehouses.transfers.destroy'));
    }

    private function context(bool $requiresExpiration = false, bool $configureDestination = true): array
    {
        $warehouse = Warehouse::factory()->create();
        $cabinet = Cabinet::factory()->for($warehouse)->create();
        $product = Product::factory()->create(['requires_expiration' => $requiresExpiration]);
        $source = InventoryItem::factory()->forWarehouse($warehouse)->for($product)->create();
        $destination = $configureDestination ? InventoryItem::factory()->forCabinet($cabinet)->for($product)->create() : null;

        return [$warehouse, $cabinet, $source, $destination];
    }

    private function batch(Warehouse $warehouse, InventoryItem $item, string $quantity, ?string $expiration = null, ?string $manufacturerLot = null, string $cost = '10.0000', ?string $createdAt = null): InventoryBatch
    {
        $entry = $warehouse->entries()->create([
            'supplier_id' => Supplier::factory()->for($warehouse)->create()->id,
            'invoice_number' => 'INV-'.(++$this->lotSequence),
            'invoice_date' => today(),
            'notes' => null,
        ]);
        $entryItem = $entry->items()->create([
            'inventory_item_id' => $item->id,
            'quantity' => $quantity,
            'unit_cost' => $cost,
            'manufacturer_lot' => $manufacturerLot,
            'expiration_date' => $expiration,
        ]);
        $batch = $entryItem->batch()->create([
            'inventory_item_id' => $item->id,
            'internal_lot' => sprintf('TEST-LOT-%06d', $this->lotSequence),
            'manufacturer_lot' => $manufacturerLot,
            'expiration_date' => $expiration,
            'received_quantity' => $quantity,
            'available_quantity' => $quantity,
            'unit_cost' => $cost,
        ]);
        if ($createdAt) {
            $batch->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
        }

        return $batch;
    }

    private function payload(Cabinet $cabinet, InventoryItem $source, string $quantity = '1.500'): array
    {
        return [
            'cabinet_id' => $cabinet->id,
            'notes' => '  Reposición interna  ',
            'items' => [['source_inventory_item_id' => $source->id, 'quantity' => $quantity]],
        ];
    }
}
