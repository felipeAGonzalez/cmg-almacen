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
use Tests\TestCase;

class InventoryTransferInterfaceTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_administrator_and_assigned_manager_see_transfer_navigation(): void
    {
        $warehouse = Warehouse::factory()->create();
        $administrator = User::factory()->administrator()->create();
        $this->actingAs($administrator)->get(route('warehouses.index'))
            ->assertOk()->assertSee(route('warehouses.transfers.index', $warehouse), false)->assertSee('Transferencias');

        $manager = User::factory()->warehouseManager()->create();
        $manager->warehouses()->attach($warehouse);
        $this->actingAs($manager)->get(route('home'))
            ->assertOk()->assertSee('MIS ALMACENES')->assertSee(route('warehouses.transfers.index', $warehouse), false);
    }

    public function test_nurse_does_not_see_transfer_navigation(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::NURSE]))
            ->get(route('home'))->assertOk()->assertDontSee('MIS ALMACENES')->assertDontSee('Transferencias');
    }

    public function test_index_displays_cabinet_actor_and_product_count_without_mutation_actions(): void
    {
        [$warehouse, $cabinet, $source, , $user] = $this->context();
        $this->batch($warehouse, $source, '5');
        $transfer = $this->transfer($warehouse, $cabinet, $source, $user, '2');

        $this->actingAs($user)->get(route('warehouses.transfers.index', $warehouse))
            ->assertOk()->assertSee($cabinet->name)->assertSee($user->name)->assertSee('1 producto')
            ->assertSee(route('warehouses.transfers.show', [$warehouse, $transfer]), false)
            ->assertDontSee('Editar')->assertDontSee('Eliminar');
    }

    public function test_create_only_displays_contextual_cabinets_and_warehouse_inventory_items(): void
    {
        [$warehouse, $cabinet, $source, , $user] = $this->context();
        $otherCabinet = Cabinet::factory()->create(['name' => 'Gabinete externo']);
        $otherSource = InventoryItem::factory()->forWarehouse(Warehouse::factory()->create())->create();
        $cabinetOnly = InventoryItem::factory()->forCabinet($cabinet)->create();

        $this->actingAs($user)->get(route('warehouses.transfers.create', $warehouse))
            ->assertOk()->assertSee($cabinet->name)->assertSee($source->product->name)
            ->assertDontSee($otherCabinet->name)->assertDontSee($otherSource->product->name)
            ->assertDontSee($cabinetOnly->product->name)
            ->assertSee('items[0][source_inventory_item_id]', false)
            ->assertSee('items[0][quantity]', false);
    }

    public function test_frontend_data_contains_cabinet_compatibility_and_usable_stock_excludes_expired_batches(): void
    {
        Carbon::setTestNow('2026-08-28');
        [$warehouse, $cabinet, $source, $destination, $user] = $this->context();
        $this->batch($warehouse, $source, '7.500', '2027-01-01');
        $this->batch($warehouse, $source, '20', '2026-08-27');
        $this->batch($warehouse, $source, '0', null);

        $this->actingAs($user)->get(route('warehouses.transfers.create', $warehouse))
            ->assertOk()->assertSee('productId')->assertSee('usableStock')
            ->assertSee((string) $cabinet->id)->assertSee((string) $destination->product_id)
            ->assertSee('7.5')->assertDontSee('&quot;usableStock&quot;:&quot;27.500&quot;', false);
    }

    public function test_validation_errors_reconstruct_multiple_rows_and_values(): void
    {
        [$warehouse, $cabinet, $firstSource, , $user] = $this->context();
        $secondProduct = Product::factory()->create();
        $secondSource = InventoryItem::factory()->forWarehouse($warehouse)->for($secondProduct)->create();
        InventoryItem::factory()->forCabinet($cabinet)->for($secondProduct)->create();

        $this->actingAs($user)->followingRedirects()->from(route('warehouses.transfers.create', $warehouse))
            ->post(route('warehouses.transfers.store', $warehouse), [
                'cabinet_id' => $cabinet->id,
                'notes' => 'Reposición',
                'items' => [
                    ['source_inventory_item_id' => $firstSource->id, 'quantity' => '1.250'],
                    ['source_inventory_item_id' => $secondSource->id, 'quantity' => '0'],
                ],
            ])->assertOk()
            ->assertSee('items[0][source_inventory_item_id]', false)
            ->assertSee('items[1][source_inventory_item_id]', false)
            ->assertSee('value="1.250"', false)
            ->assertSee('La cantidad debe ser mayor que cero.');
    }

    public function test_show_displays_allocations_and_complete_lot_traceability_without_costs(): void
    {
        Carbon::setTestNow('2026-08-28');
        [$warehouse, $cabinet, $source, , $user] = $this->context();
        $first = $this->batch($warehouse, $source, '2', '2027-01-01', 'FAB-01', '8.1250');
        $second = $this->batch($warehouse, $source, '3', null, null, '9.5000');
        $transfer = $this->transfer($warehouse, $cabinet, $source, $user, '4');
        $allocations = $transfer->items()->firstOrFail()->allocations()->with('destinationBatch')->get();

        $response = $this->actingAs($user)->get(route('warehouses.transfers.show', [$warehouse, $transfer]));
        $response->assertOk()->assertSee($source->product->name)->assertSee($source->product->unit->name)
            ->assertSee($first->internal_lot)->assertSee($second->internal_lot)->assertSee('FAB-01')
            ->assertSee('No indicado')->assertSee('01/01/2027')->assertSee('No aplica')
            ->assertSee('Cantidad transferida: 4')->assertSee('Lote en gabinete');
        foreach ($allocations as $allocation) {
            $response->assertSee($allocation->destinationBatch->internal_lot);
        }
        $response->assertDontSee('Costo')->assertDontSee('$8.1250')->assertDontSee('Editar')->assertDontSee('Eliminar');
    }

    public function test_manager_breadcrumb_does_not_link_to_global_warehouse_administration(): void
    {
        [$warehouse, $cabinet, $source] = $this->context();
        $this->batch($warehouse, $source, '3');
        $manager = User::factory()->warehouseManager()->create();
        $manager->warehouses()->attach($warehouse);
        $transfer = $this->transfer($warehouse, $cabinet, $source, $manager, '1');

        $this->actingAs($manager)->get(route('warehouses.transfers.show', [$warehouse, $transfer]))
            ->assertOk()->assertSee('Inicio')->assertDontSee('href="'.route('warehouses.index').'"', false);
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

    private function batch(Warehouse $warehouse, InventoryItem $item, string $quantity, ?string $expiration = null, ?string $manufacturerLot = null, string $cost = '10.0000'): InventoryBatch
    {
        $entry = $warehouse->entries()->create([
            'supplier_id' => Supplier::factory()->for($warehouse)->create()->id,
            'invoice_number' => 'UI-'.(++$this->sequence),
            'invoice_date' => today(),
        ]);
        $entryItem = $entry->items()->create([
            'inventory_item_id' => $item->id, 'quantity' => $quantity, 'unit_cost' => $cost,
            'manufacturer_lot' => $manufacturerLot, 'expiration_date' => $expiration,
        ]);

        return $entryItem->batch()->create([
            'inventory_item_id' => $item->id, 'internal_lot' => 'UI-LOT-'.$this->sequence,
            'manufacturer_lot' => $manufacturerLot, 'expiration_date' => $expiration,
            'received_quantity' => $quantity, 'available_quantity' => $quantity, 'unit_cost' => $cost,
        ]);
    }

    private function transfer(Warehouse $warehouse, Cabinet $cabinet, InventoryItem $source, User $user, string $quantity): InventoryTransfer
    {
        $this->actingAs($user)->post(route('warehouses.transfers.store', $warehouse), [
            'cabinet_id' => $cabinet->id,
            'notes' => null,
            'items' => [['source_inventory_item_id' => $source->id, 'quantity' => $quantity]],
        ])->assertSessionHasNoErrors();

        return InventoryTransfer::query()->latest('id')->firstOrFail();
    }
}
