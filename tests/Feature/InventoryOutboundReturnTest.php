<?php

namespace Tests\Feature;

use App\Enums\InventoryOutboundReason;
use App\Enums\UserRole;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryOutbound;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryOutboundReturnTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_manager_can_register_a_partial_manual_return_from_entries(): void
    {
        [$warehouse, $manager, $outbound, $batch, $outboundItem] = $this->scenario();

        $this->actingAs($manager)
            ->get(route('warehouses.entries.returns.create', [$warehouse, 'outbound' => $outbound]))
            ->assertOk()
            ->assertSee('Registrar devolución manual')
            ->assertSee('Salidas recientes')
            ->assertSee('Seleccionada')
            ->assertSee('Paracetamol')
            ->assertSee($batch->internal_lot);

        $this->post(route('warehouses.entries.returns.store', $warehouse), [
            'inventory_outbound_id' => $outbound->id,
            'received_at' => now()->format('Y-m-d H:i:s'),
            'notes' => 'Producto no utilizado.',
            'items' => [['outbound_item_id' => $outboundItem->id, 'quantity' => '2.500']],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('6.500', $batch->fresh()->available_quantity);
        $this->assertDatabaseHas('inventory_outbound_returns', [
            'inventory_outbound_id' => $outbound->id,
            'warehouse_id' => $warehouse->id,
            'received_by' => $manager->id,
        ]);
        $this->assertDatabaseHas('inventory_outbound_return_items', ['quantity' => '2.500']);

        $this->get(route('warehouses.kardex.index', $warehouse))
            ->assertOk()
            ->assertSee('Devolución manual — Entrada')
            ->assertSee('2.5');
    }

    public function test_returns_cannot_exceed_the_original_allocation_or_cross_warehouses(): void
    {
        [$warehouse, $manager, $outbound, $batch, $outboundItem] = $this->scenario();
        $payload = [
            'inventory_outbound_id' => $outbound->id,
            'received_at' => now()->format('Y-m-d H:i:s'),
            'items' => [['outbound_item_id' => $outboundItem->id, 'quantity' => '7.000']],
        ];

        $this->actingAs($manager)->post(route('warehouses.entries.returns.store', $warehouse), $payload)
            ->assertSessionHasErrors('items');
        $this->assertSame('4.000', $batch->fresh()->available_quantity);

        $otherWarehouse = Warehouse::factory()->create();
        $manager->warehouses()->attach($otherWarehouse);
        $this->post(route('warehouses.entries.returns.store', $otherWarehouse), $payload)
            ->assertSessionHasErrors('inventory_outbound_id');
        $this->assertDatabaseCount('inventory_outbound_returns', 0);
    }

    public function test_manual_returns_are_visible_in_entries_and_scoped_by_warehouse(): void
    {
        [$warehouse, $manager, $outbound, , $outboundItem] = $this->scenario();
        $this->actingAs($manager)->post(route('warehouses.entries.returns.store', $warehouse), [
            'inventory_outbound_id' => $outbound->id,
            'received_at' => now()->format('Y-m-d H:i:s'),
            'items' => [['outbound_item_id' => $outboundItem->id, 'quantity' => '1.000']],
        ]);

        $this->get(route('warehouses.entries.index', $warehouse))
            ->assertOk()
            ->assertSee('Devoluciones manuales recientes')
            ->assertSee('Registrar devolución manual');

        $outsider = User::factory()->create(['role' => UserRole::WAREHOUSE_MANAGER]);
        $this->actingAs($outsider)->get(route('warehouses.entries.returns.create', $warehouse))->assertForbidden();
    }

    public function test_original_outbounds_can_be_filtered_by_patient_and_date(): void
    {
        [$warehouse, $manager, $outbound] = $this->scenario();
        $other = $warehouse->inventoryOutbounds()->create([
            'reason' => InventoryOutboundReason::PATIENT_CARE,
            'patient_name' => 'Paciente Distinto',
            'processed_by' => $manager->id,
            'processed_at' => now()->subDay(),
            'source_type' => InventoryOutbound::SOURCE_MANUAL,
        ]);

        $this->actingAs($manager)
            ->get(route('warehouses.entries.returns.create', $warehouse))
            ->assertOk()
            ->assertSeeInOrder(['Paciente Manual', 'Paciente Distinto']);

        $this->get(route('warehouses.entries.returns.create', [
            $warehouse,
            'patient' => 'Paciente Manual',
            'date' => $outbound->processed_at->format('Y-m-d'),
        ]))
            ->assertOk()
            ->assertSee('Paciente Manual')
            ->assertDontSee('Paciente Distinto')
            ->assertSee('value="Paciente Manual"', false);

        $this->get(route('warehouses.entries.returns.create', [$warehouse, 'patient' => 'No existe']))
            ->assertOk()
            ->assertSee('No se encontraron salidas manuales con esos criterios.')
            ->assertDontSee('#'.$other->id.' ·');
    }

    private function scenario(): array
    {
        $warehouse = Warehouse::factory()->create();
        $manager = User::factory()->create(['role' => UserRole::WAREHOUSE_MANAGER]);
        $manager->warehouses()->attach($warehouse);
        $product = Product::factory()->create(['name' => 'Paracetamol']);
        $inventoryItem = InventoryItem::factory()->for($warehouse, 'stockable')->for($product)->create();
        $supplier = Supplier::factory()->for($warehouse)->create();
        $entry = $warehouse->entries()->create(['supplier_id' => $supplier->id, 'invoice_number' => 'DEV-BASE', 'invoice_date' => today()]);
        $entryItem = $entry->items()->create(['inventory_item_id' => $inventoryItem->id, 'quantity' => 10, 'unit_cost' => 1]);
        $batch = InventoryBatch::create([
            'inventory_item_id' => $inventoryItem->id, 'entry_item_id' => $entryItem->id,
            'internal_lot' => 'INT-RETURN-001', 'received_quantity' => 10,
            'available_quantity' => 4, 'unit_cost' => 1,
        ]);
        $outbound = $warehouse->inventoryOutbounds()->create([
            'reason' => InventoryOutboundReason::PATIENT_CARE, 'patient_name' => 'Paciente Manual',
            'processed_by' => $manager->id, 'processed_at' => now(), 'source_type' => InventoryOutbound::SOURCE_MANUAL,
        ]);
        $outboundItem = $outbound->items()->create(['inventory_item_id' => $inventoryItem->id, 'requested_quantity' => 6]);
        $outboundItem->allocations()->create(['inventory_batch_id' => $batch->id, 'quantity' => 6]);

        return [$warehouse, $manager, $outbound, $batch, $outboundItem];
    }
}
