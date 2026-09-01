<?php

namespace Tests\Feature;

use App\Enums\InventoryAdjustmentReason;
use App\Enums\UserRole;
use App\Models\Cabinet;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InventoryAdjustmentInterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_inventory_indexes_show_contextual_adjustment_actions_and_nurse_is_denied(): void
    {
        [$warehouse, $cabinet, $warehouseItem, $cabinetItem] = $this->context();

        foreach ([UserRole::ADMINISTRATOR, UserRole::ROOT] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('warehouses.inventory.index', $warehouse))
                ->assertOk()->assertSee(route('warehouses.inventory.adjustments.index', [$warehouse, $warehouseItem]), false)->assertSee('Ajustes');
        }

        $manager = User::factory()->warehouseManager()->create();
        $manager->warehouses()->attach($warehouse);
        $this->actingAs($manager)->get(route('warehouses.cabinets.inventory.index', [$warehouse, $cabinet]))
            ->assertOk()->assertSee(route('warehouses.cabinets.inventory.adjustments.index', [$warehouse, $cabinet, $cabinetItem]), false);

        $this->actingAs(User::factory()->nurse()->create())
            ->get(route('warehouses.inventory.adjustments.index', [$warehouse, $warehouseItem]))->assertForbidden();
    }

    public function test_warehouse_history_and_form_show_batches_context_reasons_and_old_input(): void
    {
        Carbon::setTestNow('2026-08-31');
        [$warehouse, , $item, , $batch, , $user] = $this->context();
        $batch->update(['manufacturer_lot' => 'FAB-100', 'expiration_date' => '2026-08-01', 'available_quantity' => '0']);
        $otherItem = InventoryItem::factory()->forWarehouse($warehouse)->create();
        $otherBatch = $this->rootBatch($warehouse, $otherItem, 'OTHER-BATCH');

        $create = route('warehouses.inventory.adjustments.create', [$warehouse, $item]);
        $this->actingAs($user)->withSession([
            '_old_input' => ['inventory_batch_id' => $batch->id, 'counted_quantity' => '2.125', 'reason' => 'counting_error', 'notes' => 'Conteo conservado'],
        ])->get($create)->assertOk()
            ->assertSee($item->product->name)->assertSee($item->product->unit->name)
            ->assertSee($batch->internal_lot)->assertSee('Existencia registrada')->assertSee('Vencido')
            ->assertDontSee($otherBatch->internal_lot)->assertSee('Conteo físico')->assertSee('Error de conteo')
            ->assertSee('Corrección del sistema')->assertSee('value="2.125"', false)->assertSee('Conteo conservado')
            ->assertDontSee('Merma')->assertDontSee('Daño');

        $first = $item->adjustments()->create([
            'inventory_batch_id' => $batch->id,
            'previous_quantity' => '1', 'counted_quantity' => '3', 'difference' => '2',
            'reason' => InventoryAdjustmentReason::PHYSICAL_COUNT, 'adjusted_by' => $user->id, 'adjusted_at' => now(),
        ]);
        $item->adjustments()->create([
            'inventory_batch_id' => $batch->id,
            'previous_quantity' => '3', 'counted_quantity' => '0', 'difference' => '-3',
            'reason' => InventoryAdjustmentReason::COUNTING_ERROR, 'adjusted_by' => $user->id, 'adjusted_at' => now()->addMinute(),
        ]);

        $this->get(route('warehouses.inventory.adjustments.index', [$warehouse, $item]))->assertOk()
            ->assertSee('Producto: '.$item->product->name)->assertSee('Almacén: '.$warehouse->name)
            ->assertSee('+2')->assertSee('-3')->assertSee($user->name)
            ->assertSee(route('warehouses.inventory.adjustments.show', [$warehouse, $item, $first]), false);
    }

    public function test_cabinet_form_only_shows_its_derived_batches_and_context(): void
    {
        [$warehouse, $cabinet, $warehouseItem, $cabinetItem, $rootBatch, $derivedBatch, $user] = $this->context();

        $this->actingAs($user)->get(route('warehouses.cabinets.inventory.adjustments.create', [$warehouse, $cabinet, $cabinetItem]))
            ->assertOk()->assertSee('Gabinete: '.$cabinet->name)->assertSee($cabinetItem->product->name)
            ->assertSee($derivedBatch->internal_lot)->assertDontSee($rootBatch->internal_lot)
            ->assertSee('data-adjustment-batches=', false);
    }

    public function test_detail_is_historical_has_kardex_link_and_never_exposes_cost_or_mutation_actions(): void
    {
        [$warehouse, , $item, , $batch, , $user] = $this->context();
        $batch->update(['manufacturer_lot' => null, 'expiration_date' => null]);
        $adjustment = $item->adjustments()->create([
            'inventory_batch_id' => $batch->id,
            'previous_quantity' => '10', 'counted_quantity' => '12.5', 'difference' => '2.5',
            'reason' => InventoryAdjustmentReason::SYSTEM_CORRECTION, 'notes' => 'Validado',
            'adjusted_by' => $user->id, 'adjusted_at' => now(),
        ]);

        $this->actingAs($user)->get(route('warehouses.inventory.adjustments.show', [$warehouse, $item, $adjustment]))
            ->assertOk()->assertSee('Detalle de ajuste')->assertSee('No indicado')->assertSee('Sin caducidad')
            ->assertSee('+2.5')->assertSee('Aumento de existencia')->assertSee('Validado')
            ->assertSee(route('warehouses.kardex.index', [$warehouse, 'inventory_item_id' => $item->id]), false)
            ->assertDontSee('Costo')->assertDontSee('Editar')->assertDontSee('Eliminar');
    }

    private function context(): array
    {
        $warehouse = Warehouse::factory()->create();
        $cabinet = Cabinet::factory()->for($warehouse)->create();
        $product = Product::factory()->create();
        $warehouseItem = InventoryItem::factory()->forWarehouse($warehouse)->for($product)->create();
        $cabinetItem = InventoryItem::factory()->forCabinet($cabinet)->for($product)->create();
        $root = $this->rootBatch($warehouse, $warehouseItem, 'ROOT-'.uniqid());
        $derived = InventoryBatch::query()->create([
            'inventory_item_id' => $cabinetItem->id, 'source_batch_id' => $root->id,
            'internal_lot' => 'CAB-'.uniqid(), 'received_quantity' => '2',
            'available_quantity' => '2', 'unit_cost' => '1',
        ]);

        return [$warehouse, $cabinet, $warehouseItem, $cabinetItem, $root, $derived, User::factory()->administrator()->create()];
    }

    private function rootBatch(Warehouse $warehouse, InventoryItem $item, string $lot): InventoryBatch
    {
        $entry = $warehouse->entries()->create([
            'supplier_id' => Supplier::factory()->for($warehouse)->create()->id,
            'invoice_number' => $lot, 'invoice_date' => today(),
        ]);
        $entryItem = $entry->items()->create(['inventory_item_id' => $item->id, 'quantity' => '10', 'unit_cost' => '1']);

        return $entryItem->batch()->create([
            'inventory_item_id' => $item->id, 'internal_lot' => $lot,
            'received_quantity' => '10', 'available_quantity' => '10', 'unit_cost' => '1',
        ]);
    }
}
