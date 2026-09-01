<?php

namespace Tests\Feature;

use App\Enums\InventoryAdjustmentReason;
use App\Enums\UserRole;
use App\Models\Cabinet;
use App\Models\InventoryAdjustment;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InventoryAdjustmentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_roles_can_access_their_warehouse_and_cabinet_adjustments(): void
    {
        [$warehouse, $cabinet, $warehouseItem, $cabinetItem] = $this->context();

        foreach ([UserRole::ADMINISTRATOR, UserRole::ROOT] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('warehouses.inventory.adjustments.index', [$warehouse, $warehouseItem]))->assertOk();
            $this->get(route('warehouses.cabinets.inventory.adjustments.index', [$warehouse, $cabinet, $cabinetItem]))->assertOk();
        }

        $manager = User::factory()->warehouseManager()->create();
        $manager->warehouses()->attach($warehouse);
        $this->actingAs($manager)->get(route('warehouses.inventory.adjustments.index', [$warehouse, $warehouseItem]))->assertOk();
        $this->get(route('warehouses.cabinets.inventory.adjustments.index', [$warehouse, $cabinet, $cabinetItem]))->assertOk();
        $other = Warehouse::factory()->create();
        $otherItem = InventoryItem::factory()->forWarehouse($other)->create();
        $this->get(route('warehouses.inventory.adjustments.index', [$other, $otherItem]))->assertForbidden();

        foreach ([UserRole::NURSE, UserRole::LEGACY_USER] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('warehouses.inventory.adjustments.index', [$warehouse, $warehouseItem]))->assertForbidden();
        }
        auth()->logout();
        $this->get(route('warehouses.inventory.adjustments.index', [$warehouse, $warehouseItem]))->assertRedirect(route('login'));
    }

    public function test_scoped_context_rejects_mismatched_items_cabinets_and_batches(): void
    {
        [$warehouse, $cabinet, $warehouseItem, $cabinetItem, $warehouseBatch, $cabinetBatch, $user] = $this->context();
        [$otherWarehouse, $otherCabinet, $otherItem] = $this->context();

        $this->actingAs($user)->get(route('warehouses.inventory.adjustments.index', [$warehouse, $otherItem]))->assertNotFound();
        $this->get(route('warehouses.cabinets.inventory.adjustments.index', [$warehouse, $otherCabinet, $cabinetItem]))->assertNotFound();
        $this->post(route('warehouses.inventory.adjustments.store', [$warehouse, $warehouseItem]), $this->payload($cabinetBatch->id, '2'))
            ->assertSessionHasErrors('inventory_batch_id');
        $this->post(route('warehouses.cabinets.inventory.adjustments.store', [$warehouse, $cabinet, $cabinetItem]), $this->payload($warehouseBatch->id, '2'))
            ->assertSessionHasErrors('inventory_batch_id');
        $this->assertNotSame($otherWarehouse->id, $warehouse->id);
    }

    public function test_validation_normalization_and_enum_rules_are_enforced(): void
    {
        [$warehouse, , $item, , $batch, , $user] = $this->context();
        $route = route('warehouses.inventory.adjustments.store', [$warehouse, $item]);

        $this->actingAs($user)->post($route, [])->assertSessionHasErrors(['inventory_batch_id', 'counted_quantity', 'reason']);
        $this->post($route, $this->payload($batch->id, '-1'))->assertSessionHasErrors('counted_quantity');
        $this->post($route, $this->payload($batch->id, '1.2345'))->assertSessionHasErrors('counted_quantity');
        $payload = $this->payload($batch->id, '12.125');
        $payload['reason'] = 'invalid';
        $this->post($route, $payload)->assertSessionHasErrors('reason');

        $payload = $this->payload($batch->id, '12.125');
        $payload['notes'] = '  Conteo de cierre  ';
        $this->post($route, $payload)->assertSessionHasNoErrors();
        $this->assertSame('Conteo de cierre', InventoryAdjustment::firstOrFail()->notes);
    }

    public function test_positive_and_negative_adjustments_update_only_available_quantity(): void
    {
        [$warehouse, , $item, , $batch, , $user] = $this->context();
        $received = $batch->received_quantity;
        $route = route('warehouses.inventory.adjustments.store', [$warehouse, $item]);

        $this->actingAs($user)->post($route, $this->payload($batch->id, '12.500'))->assertSessionHasNoErrors();
        $positive = InventoryAdjustment::firstOrFail();
        $this->assertSame('10.000', $positive->previous_quantity);
        $this->assertSame('12.500', $positive->counted_quantity);
        $this->assertSame('2.500', $positive->difference);
        $this->assertSame($user->id, $positive->adjusted_by);
        $this->assertNotNull($positive->adjusted_at);
        $this->assertSame('12.500', $batch->fresh()->available_quantity);
        $this->assertSame($received, $batch->fresh()->received_quantity);

        $this->post($route, $this->payload($batch->id, '0'))->assertSessionHasNoErrors();
        $negative = InventoryAdjustment::latest('id')->firstOrFail();
        $this->assertSame('-12.500', $negative->difference);
        $this->assertSame('0.000', $batch->fresh()->available_quantity);
        $this->assertSame($received, $batch->fresh()->received_quantity);
    }

    public function test_equal_count_does_not_create_history_or_change_the_locked_batch(): void
    {
        [$warehouse, , $item, , $batch, , $user] = $this->context();

        $this->actingAs($user)->post(route('warehouses.inventory.adjustments.store', [$warehouse, $item]), $this->payload($batch->id, '10'))
            ->assertSessionHasErrors(['counted_quantity' => 'La cantidad contada coincide con la existencia registrada.']);

        $this->assertDatabaseCount('inventory_adjustments', 0);
        $this->assertSame('10.000', $batch->fresh()->available_quantity);
    }

    public function test_expired_zero_and_derived_cabinet_batches_can_be_adjusted_locally(): void
    {
        Carbon::setTestNow('2026-08-31');
        [$warehouse, $cabinet, $warehouseItem, $cabinetItem, $warehouseBatch, $cabinetBatch, $user] = $this->context();
        $warehouseBatch->update(['expiration_date' => '2026-08-01', 'available_quantity' => '0']);

        $this->actingAs($user)->post(route('warehouses.inventory.adjustments.store', [$warehouse, $warehouseItem]), $this->payload($warehouseBatch->id, '1.250'))
            ->assertSessionHasNoErrors();
        $rootQuantity = $warehouseBatch->fresh()->available_quantity;
        $this->post(route('warehouses.cabinets.inventory.adjustments.store', [$warehouse, $cabinet, $cabinetItem]), $this->payload($cabinetBatch->id, '3.750'))
            ->assertSessionHasNoErrors();

        $this->assertSame('3.750', $cabinetBatch->fresh()->available_quantity);
        $this->assertSame($rootQuantity, $warehouseBatch->fresh()->available_quantity);
    }

    public function test_adjustments_are_immutable_and_protect_inventory_configuration(): void
    {
        [$warehouse, , $item, , $batch, , $user] = $this->context();
        $this->actingAs($user)->post(route('warehouses.inventory.adjustments.store', [$warehouse, $item]), $this->payload($batch->id, '9'));
        $adjustment = InventoryAdjustment::firstOrFail();

        $this->get(route('warehouses.inventory.adjustments.show', [$warehouse, $item, $adjustment]))->assertOk();
        $this->assertFalse(app('router')->getRoutes()->hasNamedRoute('warehouses.inventory.adjustments.edit'));
        $this->assertFalse(app('router')->getRoutes()->hasNamedRoute('warehouses.inventory.adjustments.update'));
        $this->assertFalse(app('router')->getRoutes()->hasNamedRoute('warehouses.inventory.adjustments.destroy'));
        $this->delete(route('warehouses.inventory.destroy', [$warehouse, $item]))
            ->assertSessionHas('error', 'No se puede retirar el producto porque tiene movimientos de inventario registrados.');
        $this->assertDatabaseHas('inventory_items', ['id' => $item->id]);
        $this->assertDatabaseHas('inventory_adjustments', ['id' => $adjustment->id]);
    }

    private function context(): array
    {
        $warehouse = Warehouse::factory()->create();
        $cabinet = Cabinet::factory()->for($warehouse)->create();
        $product = Product::factory()->create();
        $warehouseItem = InventoryItem::factory()->forWarehouse($warehouse)->for($product)->create();
        $cabinetItem = InventoryItem::factory()->forCabinet($cabinet)->for($product)->create();
        $warehouseBatch = $this->batch($warehouse, $warehouseItem, 'ADJ-ROOT-'.uniqid());
        $cabinetBatch = InventoryBatch::query()->create([
            'inventory_item_id' => $cabinetItem->id,
            'source_batch_id' => $warehouseBatch->id,
            'internal_lot' => 'ADJ-CAB-'.uniqid(),
            'received_quantity' => '2',
            'available_quantity' => '2',
            'unit_cost' => '1',
        ]);

        return [$warehouse, $cabinet, $warehouseItem, $cabinetItem, $warehouseBatch, $cabinetBatch, User::factory()->administrator()->create()];
    }

    private function batch(Warehouse $warehouse, InventoryItem $item, string $lot)
    {
        $entry = $warehouse->entries()->create([
            'supplier_id' => Supplier::factory()->for($warehouse)->create()->id,
            'invoice_number' => $lot,
            'invoice_date' => today(),
        ]);
        $entryItem = $entry->items()->create([
            'inventory_item_id' => $item->id,
            'quantity' => '10',
            'unit_cost' => '1',
        ]);

        return $entryItem->batch()->create([
            'inventory_item_id' => $item->id,
            'internal_lot' => $lot,
            'received_quantity' => '10',
            'available_quantity' => '10',
            'unit_cost' => '1',
        ]);
    }

    private function payload(int $batchId, string $counted): array
    {
        return [
            'inventory_batch_id' => $batchId,
            'counted_quantity' => $counted,
            'reason' => InventoryAdjustmentReason::PHYSICAL_COUNT->value,
            'notes' => '',
        ];
    }
}
