<?php

namespace Tests\Feature;

use App\Enums\NursingSupplySourceType;
use App\Enums\NursingVoucherReturnStatus;
use App\Enums\NursingVoucherStatus;
use App\Enums\UserRole;
use App\Models\Cabinet;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\NursingVoucher;
use App\Models\NursingVoucherReturn;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NursingVoucherReturnTest extends TestCase
{
    use RefreshDatabase;

    public function test_nurse_can_request_one_product_or_multiple_partial_products_without_changing_stock(): void
    {
        [$voucher, $nurse, $warehouse, $firstItem, $firstBatch] = $this->voucherWithSuppliedItem('10', '10');
        [$secondItem, $secondBatch] = $this->addSuppliedItem($voucher, '5', '5');

        $this->actingAs($nurse)->post(route('nursing-vouchers.returns.store', $voucher), [
            'items' => [
                ['voucher_item_id' => $firstItem->id, 'quantity' => '3.500'],
                ['voucher_item_id' => $secondItem->id, 'quantity' => '2'],
            ],
            'notes' => 'Productos sin utilizar',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $return = NursingVoucherReturn::query()->firstOrFail();
        $this->assertSame(NursingVoucherReturnStatus::PENDING, $return->status);
        $this->assertSame(0, bccomp((string) $return->items()->sum('quantity'), '5.500', 3));
        $this->assertSame('10.000', $firstBatch->fresh()->available_quantity);
        $this->assertSame('5.000', $secondBatch->fresh()->available_quantity);
        $this->assertSame($warehouse->id, $voucher->warehouse_id);
    }

    public function test_assigned_manager_receives_return_and_restores_original_batch(): void
    {
        [$voucher, $nurse, $warehouse, $item, $batch] = $this->voucherWithSuppliedItem('10', '10');
        $manager = User::factory()->create(['role' => UserRole::WAREHOUSE_MANAGER]);
        $manager->warehouses()->attach($warehouse);

        $this->actingAs($nurse)->post(route('nursing-vouchers.returns.store', $voucher), [
            'items' => [['voucher_item_id' => $item->id, 'quantity' => '4']],
        ]);
        $return = NursingVoucherReturn::query()->firstOrFail();
        $this->assertTrue($manager->fresh()->unreadNotifications()
            ->where('data->kind', 'return_action_required')
            ->where('data->nursing_voucher_return_id', $return->id)
            ->exists());

        $this->actingAs($manager)->post(route('nursing-vouchers.returns.receive', [$voucher, $return]))
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(NursingVoucherReturnStatus::RECEIVED, $return->fresh()->status);
        $this->assertSame($manager->id, $return->fresh()->received_by);
        $this->assertSame('14.000', $batch->fresh()->available_quantity);
        $this->assertNotNull($return->fresh()->received_at);
        $this->assertTrue($nurse->fresh()->unreadNotifications()
            ->where('data->kind', 'return_received')
            ->where('data->nursing_voucher_return_id', $return->id)
            ->exists());
    }

    public function test_pending_and_received_returns_prevent_over_return(): void
    {
        [$voucher, $nurse, , $item] = $this->voucherWithSuppliedItem('10', '10');

        $this->actingAs($nurse)->post(route('nursing-vouchers.returns.store', $voucher), [
            'items' => [['voucher_item_id' => $item->id, 'quantity' => '7']],
        ])->assertSessionHasNoErrors();

        $this->actingAs($nurse)->post(route('nursing-vouchers.returns.store', $voucher), [
            'items' => [['voucher_item_id' => $item->id, 'quantity' => '4']],
        ])->assertSessionHasErrors('items');

        $this->assertDatabaseCount('nursing_voucher_returns', 1);
    }

    public function test_unassigned_manager_cannot_receive_and_requester_can_cancel_pending_return(): void
    {
        [$voucher, $nurse, , $item, $batch] = $this->voucherWithSuppliedItem('10', '10');
        $outsider = User::factory()->create(['role' => UserRole::WAREHOUSE_MANAGER]);

        $this->actingAs($nurse)->post(route('nursing-vouchers.returns.store', $voucher), [
            'items' => [['voucher_item_id' => $item->id, 'quantity' => '2']],
        ]);
        $return = NursingVoucherReturn::query()->firstOrFail();

        $this->actingAs($outsider)->post(route('nursing-vouchers.returns.receive', [$voucher, $return]))
            ->assertForbidden();
        $this->actingAs($nurse)->post(route('nursing-vouchers.returns.cancel', [$voucher, $return]))
            ->assertRedirect();

        $this->assertSame(NursingVoucherReturnStatus::CANCELLED, $return->fresh()->status);
        $this->assertSame('10.000', $batch->fresh()->available_quantity);
    }

    public function test_received_return_appears_as_kardex_entry(): void
    {
        [$voucher, $nurse, $warehouse, $item] = $this->voucherWithSuppliedItem('10', '10');
        $manager = User::factory()->create(['role' => UserRole::WAREHOUSE_MANAGER]);
        $manager->warehouses()->attach($warehouse);

        $this->actingAs($nurse)->post(route('nursing-vouchers.returns.store', $voucher), [
            'items' => [['voucher_item_id' => $item->id, 'quantity' => '3']],
        ]);
        $return = NursingVoucherReturn::query()->firstOrFail();
        $this->actingAs($manager)->post(route('nursing-vouchers.returns.receive', [$voucher, $return]));

        $this->actingAs($manager)->get(route('warehouses.kardex.index', [
            $warehouse,
            'movement_type' => 'nursing_voucher_warehouse_return',
        ]))->assertOk()
            ->assertSee('Devolución de Enfermería — Entrada a almacén')
            ->assertSee('3');
    }

    public function test_same_warehouse_nurse_receives_cabinet_return_but_manager_cannot(): void
    {
        [$voucher, $requester, $warehouse, $item, $warehouseBatch] = $this->voucherWithSuppliedItem('6', '4');
        $cabinet = Cabinet::factory()->for($warehouse)->create();
        $cabinetInventory = InventoryItem::factory()->forCabinet($cabinet)->for($item->product)->create();
        $cabinetBatch = InventoryBatch::query()->create([
            'inventory_item_id' => $cabinetInventory->id,
            'internal_lot' => 'CABINET-RETURN',
            'received_quantity' => '10',
            'available_quantity' => '4',
            'unit_cost' => '10.0000',
        ]);
        $item->fulfillmentItems()->firstOrFail()->allocations()->firstOrFail()->update([
            'inventory_batch_id' => $cabinetBatch->id,
        ]);
        $voucher->update([
            'source_type' => NursingSupplySourceType::CABINET,
            'source_cabinet_id' => $cabinet->id,
        ]);
        $receiver = User::factory()->create(['role' => UserRole::NURSE]);
        $receiver->warehouses()->attach($warehouse);
        $manager = User::factory()->create(['role' => UserRole::WAREHOUSE_MANAGER]);
        $manager->warehouses()->attach($warehouse);

        $this->actingAs($requester)->post(route('nursing-vouchers.returns.store', $voucher), [
            'items' => [['voucher_item_id' => $item->id, 'quantity' => '2']],
        ]);
        $return = NursingVoucherReturn::query()->firstOrFail();

        $this->actingAs($manager)->post(route('nursing-vouchers.returns.receive', [$voucher, $return]))
            ->assertForbidden();
        $this->actingAs($receiver)->post(route('nursing-vouchers.returns.receive', [$voucher, $return]))
            ->assertRedirect();

        $this->assertSame('6.000', $cabinetBatch->fresh()->available_quantity);
        $this->assertSame('4.000', $warehouseBatch->fresh()->available_quantity);
    }

    public function test_show_exposes_full_and_partial_return_controls_and_status(): void
    {
        [$voucher, $nurse, , $item] = $this->voucherWithSuppliedItem('10', '10');

        $this->actingAs($nurse)->get(route('nursing-vouchers.show', $voucher))
            ->assertOk()
            ->assertSee('Solicitar devolución')
            ->assertSee('Devolver todo el vale')
            ->assertSee('Devolver completo')
            ->assertSee('max="10.000"', false);

        $this->actingAs($nurse)->post(route('nursing-vouchers.returns.store', $voucher), [
            'items' => [['voucher_item_id' => $item->id, 'quantity' => '1']],
        ]);

        $this->actingAs($nurse)->get(route('nursing-vouchers.show', $voucher))
            ->assertOk()
            ->assertSee('Devoluciones')
            ->assertSee('Pendiente de recepción');
    }

    public function test_pending_return_is_expanded_for_assigned_manager_with_receive_action(): void
    {
        [$voucher, $nurse, $warehouse, $item] = $this->voucherWithSuppliedItem('10', '10');
        $manager = User::factory()->create(['role' => UserRole::WAREHOUSE_MANAGER]);
        $manager->warehouses()->attach($warehouse);

        $this->actingAs($nurse)->post(route('nursing-vouchers.returns.store', $voucher), [
            'items' => [['voucher_item_id' => $item->id, 'quantity' => '2']],
        ]);
        $return = NursingVoucherReturn::query()->firstOrFail();

        $this->actingAs($manager)->get(route('nursing-vouchers.show', $voucher))
            ->assertOk()
            ->assertSee('Requiere recepción')
            ->assertSee('Confirmar recepción')
            ->assertSee('id="return-'.$return->id.'" class="accordion-collapse collapse show"', false);
    }

    private function voucherWithSuppliedItem(string $supplied, string $available): array
    {
        $warehouse = Warehouse::factory()->create();
        $nurse = User::factory()->create(['role' => UserRole::NURSE]);
        $nurse->warehouses()->attach($warehouse);
        $voucher = NursingVoucher::query()->create([
            'requested_by' => $nurse->id,
            'warehouse_id' => $warehouse->id,
            'source_type' => NursingSupplySourceType::WAREHOUSE,
            'external_patient_id' => 'patient-return',
            'external_hospitalization_id' => 'stay-return',
            'patient_name' => 'Paciente devolución',
            'external_room_id' => 'room-return',
            'room_number' => '204',
            'status' => NursingVoucherStatus::SUPPLIED,
            'requested_at' => now(),
            'completed_at' => now(),
        ]);
        [$item, $batch] = $this->addSuppliedItem($voucher, $supplied, $available);

        return [$voucher, $nurse, $warehouse, $item, $batch];
    }

    private function addSuppliedItem(NursingVoucher $voucher, string $supplied, string $available): array
    {
        $product = Product::factory()->create();
        $inventoryItem = InventoryItem::factory()->forWarehouse($voucher->warehouse)->for($product)->create();
        $batch = InventoryBatch::query()->create([
            'inventory_item_id' => $inventoryItem->id,
            'internal_lot' => 'RETURN-'.fake()->unique()->numerify('####'),
            'received_quantity' => bcadd($supplied, $available, 3),
            'available_quantity' => $available,
            'unit_cost' => '10.0000',
        ]);
        $item = $voucher->items()->create([
            'product_id' => $product->id,
            'requested_quantity' => $supplied,
            'supplied_quantity' => $supplied,
        ]);
        $fulfillment = $voucher->fulfillments()->create([
            'supplied_by' => User::factory()->administrator()->create()->id,
            'supplied_at' => now(),
        ]);
        $fulfillmentItem = $fulfillment->items()->create([
            'nursing_voucher_item_id' => $item->id,
            'quantity' => $supplied,
        ]);
        $fulfillmentItem->allocations()->create([
            'inventory_batch_id' => $batch->id,
            'quantity' => $supplied,
        ]);

        return [$item, $batch];
    }
}
