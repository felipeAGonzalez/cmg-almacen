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
use Tests\TestCase;

class InventoryOutboundInterfaceTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_authorized_navigation_is_visible_and_nurse_does_not_see_it(): void
    {
        $warehouse = Warehouse::factory()->create();
        foreach ([UserRole::ADMINISTRATOR, UserRole::ROOT] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('warehouses.index'))
                ->assertOk()->assertSee(route('warehouses.outbounds.index', $warehouse), false)->assertSee('Salidas');
        }
        $manager = User::factory()->warehouseManager()->create();
        $manager->warehouses()->attach($warehouse);
        $this->actingAs($manager)->get(route('home'))->assertOk()
            ->assertSee(route('warehouses.outbounds.index', $warehouse), false)->assertSee('Salidas');
        $this->actingAs(User::factory()->create(['role' => UserRole::NURSE]))->get(route('home'))
            ->assertOk()->assertDontSee('MIS ALMACENES')->assertDontSee('Salidas');
    }

    public function test_index_displays_spanish_reason_actor_count_and_only_detail_action(): void
    {
        [$warehouse, $item, $user] = $this->context();
        $this->batch($warehouse, $item, '5');
        $outbound = $this->outbound($warehouse, $item, $user, '1');

        $this->actingAs($user)->get(route('warehouses.outbounds.index', $warehouse))->assertOk()
            ->assertSee('Daño')->assertDontSee('damage')->assertSee($user->name)->assertSee('1 producto')
            ->assertSee(route('warehouses.outbounds.show', [$warehouse, $outbound]), false)
            ->assertDontSee('Editar')->assertDontSee('Eliminar');
    }

    public function test_create_only_displays_warehouse_inventory_and_manual_reasons(): void
    {
        Carbon::setTestNow('2026-08-31');
        [$warehouse, $item, $user] = $this->context();
        $this->batch($warehouse, $item, '7.500', '2027-01-01');
        $this->batch($warehouse, $item, '20', '2026-08-30');
        $other = InventoryItem::factory()->forWarehouse(Warehouse::factory()->create())->create();
        $cabinetItem = InventoryItem::factory()->forCabinet(Cabinet::factory()->for($warehouse)->create())->create();

        $response = $this->actingAs($user)->get(route('warehouses.outbounds.create', $warehouse));
        $response->assertOk()->assertSee($item->product->name)->assertDontSee($other->product->name)
            ->assertDontSee($cabinetItem->product->name)->assertSee('usableStock')->assertSee('7.5')
            ->assertSee('items[0][inventory_item_id]', false)->assertSee('items[0][quantity]', false);
        foreach (InventoryOutboundReason::cases() as $reason) {
            $response->assertSee($reason->label());
        }
        $response->assertDontSee('Vale de enfermería')->assertDontSee('Vale de administración')->assertDontSee('Reposición de gabinete');
    }

    public function test_validation_errors_reconstruct_multiple_rows_and_values(): void
    {
        [$warehouse, $first, $user] = $this->context();
        $second = InventoryItem::factory()->forWarehouse($warehouse)->create();

        $this->actingAs($user)->followingRedirects()->from(route('warehouses.outbounds.create', $warehouse))
            ->post(route('warehouses.outbounds.store', $warehouse), [
                'reason' => InventoryOutboundReason::DAMAGE->value,
                'notes' => 'Comentario conservado',
                'items' => [
                    ['inventory_item_id' => $first->id, 'quantity' => '1.250'],
                    ['inventory_item_id' => $second->id, 'quantity' => '0'],
                ],
            ])->assertOk()->assertSee('Comentario conservado')
            ->assertSee('items[0][inventory_item_id]', false)->assertSee('items[1][inventory_item_id]', false)
            ->assertSee('value="1.250"', false)->assertSee('La cantidad debe ser mayor que cero.');
    }

    public function test_show_displays_allocations_and_lot_data_without_costs_or_mutation_actions(): void
    {
        [$warehouse, $item, $user] = $this->context();
        $first = $this->batch($warehouse, $item, '2', '2027-01-01', 'FAB-01');
        $second = $this->batch($warehouse, $item, '3');
        $outbound = $this->outbound($warehouse, $item, $user, '4');

        $this->actingAs($user)->get(route('warehouses.outbounds.show', [$warehouse, $outbound]))->assertOk()
            ->assertSee($item->product->name)->assertSee($item->product->unit->name)
            ->assertSee($first->internal_lot)->assertSee($second->internal_lot)->assertSee('FAB-01')
            ->assertSee('No indicado')->assertSee('01/01/2027')->assertSee('No aplica')
            ->assertSee('Cantidad retirada: 4')->assertDontSee('Costo')->assertDontSee('Editar')->assertDontSee('Eliminar');
    }

    public function test_manager_breadcrumb_does_not_link_to_global_warehouse_administration(): void
    {
        [$warehouse, $item] = $this->context();
        $manager = User::factory()->warehouseManager()->create();
        $manager->warehouses()->attach($warehouse);
        $this->batch($warehouse, $item, '2');
        $outbound = $this->outbound($warehouse, $item, $manager, '1');

        $this->actingAs($manager)->get(route('warehouses.outbounds.show', [$warehouse, $outbound]))
            ->assertOk()->assertSee('Inicio')->assertDontSee('href="'.route('warehouses.index').'"', false);
    }

    private function context(): array
    {
        $warehouse = Warehouse::factory()->create();
        $item = InventoryItem::factory()->forWarehouse($warehouse)->for(Product::factory()->create())->create();

        return [$warehouse, $item, User::factory()->administrator()->create()];
    }

    private function batch(Warehouse $warehouse, InventoryItem $item, string $quantity, ?string $expiration = null, ?string $manufacturerLot = null): InventoryBatch
    {
        $entry = $warehouse->entries()->create([
            'supplier_id' => Supplier::factory()->for($warehouse)->create()->id,
            'invoice_number' => 'OUT-UI-'.(++$this->sequence), 'invoice_date' => today(),
        ]);
        $entryItem = $entry->items()->create([
            'inventory_item_id' => $item->id, 'quantity' => $quantity, 'unit_cost' => '13.2500',
            'manufacturer_lot' => $manufacturerLot, 'expiration_date' => $expiration,
        ]);

        return $entryItem->batch()->create([
            'inventory_item_id' => $item->id, 'internal_lot' => 'OUT-UI-LOT-'.$this->sequence,
            'manufacturer_lot' => $manufacturerLot, 'expiration_date' => $expiration,
            'received_quantity' => $quantity, 'available_quantity' => $quantity, 'unit_cost' => '13.2500',
        ]);
    }

    private function outbound(Warehouse $warehouse, InventoryItem $item, User $user, string $quantity): InventoryOutbound
    {
        $this->actingAs($user)->post(route('warehouses.outbounds.store', $warehouse), [
            'reason' => InventoryOutboundReason::DAMAGE->value, 'notes' => null,
            'items' => [['inventory_item_id' => $item->id, 'quantity' => $quantity]],
        ])->assertSessionHasNoErrors();

        return InventoryOutbound::query()->latest('id')->firstOrFail();
    }
}
