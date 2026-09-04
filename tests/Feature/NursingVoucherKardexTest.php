<?php

namespace Tests\Feature;

use App\Enums\NursingSupplySourceType;
use App\Enums\NursingVoucherStatus;
use App\Enums\UserRole;
use App\Models\Cabinet;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\NursingVoucher;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryKardexService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NursingVoucherKardexTest extends TestCase
{
    use RefreshDatabase;

    public function test_warehouse_allocations_are_individual_kardex_outputs_with_fulfillment_data(): void
    {
        [$warehouse, , $warehouseItem, , $requester, $supplier] = $this->context();
        $firstBatch = $this->batch($warehouseItem, 'W-LOT-A', 'FAB-A', '2027-03-15');
        $secondBatch = $this->batch($warehouseItem, 'W-LOT-B');
        $voucher = $this->voucher($warehouse, $requester, NursingSupplySourceType::WAREHOUSE);
        $item = $voucher->items()->create(['product_id' => $warehouseItem->product_id, 'requested_quantity' => '10', 'supplied_quantity' => '10']);
        $this->fulfillment($voucher, $item->id, $supplier, '2026-09-01 10:00:00', [[$firstBatch, '4'], [$secondBatch, '2']]);
        $this->fulfillment($voucher, $item->id, $supplier, '2026-09-02 11:30:00', [[$secondBatch, '4']]);

        $rows = app(InventoryKardexService::class)->forWarehouse($warehouse, [
            'movement_type' => InventoryKardexService::NURSING_VOUCHER_WAREHOUSE_OUT,
        ]);

        $this->assertSame(3, $rows->total());
        $this->assertSame(['4', '2', '4'], $rows->pluck('formatted_quantity')->all());
        $this->assertSame('2026-09-02 11:30', $rows->first()->occurred_at->format('Y-m-d H:i'));
        $this->assertSame($supplier->name, $rows->first()->actor_name);
        $this->assertSame($voucher->id, $rows->first()->reference_id);
        $this->assertSame('Paciente Kardex', $rows->first()->patient_name);
        $this->assertSame('204', $rows->first()->room_number);
        $this->assertSame(['W-LOT-A', 'W-LOT-B'], $rows->pluck('internal_lot')->unique()->sort()->values()->all());
        $this->assertSame(2, app(InventoryKardexService::class)->forWarehouse($warehouse, ['date_to' => '2026-09-01'])->total());
        $this->assertSame(1, app(InventoryKardexService::class)->forWarehouse($warehouse, ['date_from' => '2026-09-02'])->total());
        $this->assertSame(3, app(InventoryKardexService::class)->forWarehouse($warehouse, ['inventory_item_id' => $warehouseItem->id])->total());

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('warehouses.kardex.index', [$warehouse, 'movement_type' => InventoryKardexService::NURSING_VOUCHER_WAREHOUSE_OUT]))
            ->assertOk()->assertSee('Vale de Enfermería — Salida de almacén')
            ->assertSee('Paciente Kardex')->assertSee('Habitación 204')
            ->assertSee('FAB-A')->assertSee('Caduca: 15/03/2027')
            ->assertSee(route('nursing-vouchers.show', $voucher), false);
    }

    public function test_cabinet_allocation_uses_cabinet_inventory_and_authorized_link_rules(): void
    {
        [$warehouse, $cabinet, , $cabinetItem, $requester, $supplier] = $this->context();
        $batch = $this->batch($cabinetItem, 'C-LOT-A', 'CAB-FAB');
        $voucher = $this->voucher($warehouse, $requester, NursingSupplySourceType::CABINET, $cabinet);
        $item = $voucher->items()->create(['product_id' => $cabinetItem->product_id, 'requested_quantity' => '3', 'supplied_quantity' => '1.5']);
        $this->fulfillment($voucher, $item->id, $supplier, '2026-09-02 20:00:00', [[$batch, '1.5']]);

        $rows = app(InventoryKardexService::class)->forCabinet($warehouse, $cabinet, [
            'movement_type' => InventoryKardexService::NURSING_VOUCHER_CABINET_OUT,
        ]);
        $this->assertSame(1, $rows->total());
        $this->assertSame($cabinetItem->id, $rows->first()->inventory_item_id);
        $this->assertSame('C-LOT-A', $rows->first()->internal_lot);
        $this->assertSame('1.5', $rows->first()->formatted_quantity);

        $manager = User::factory()->create(['role' => UserRole::WAREHOUSE_MANAGER]);
        $manager->warehouses()->attach($warehouse);
        $this->actingAs($manager)->get(route('warehouses.cabinets.kardex.index', [$warehouse, $cabinet]))
            ->assertOk()->assertSee('Vale de Enfermería #'.$voucher->id)
            ->assertDontSee('href="'.route('nursing-vouchers.show', $voucher).'"', false);
        $this->get(route('nursing-vouchers.show', $voucher))->assertForbidden();

        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin)->get(route('warehouses.cabinets.kardex.index', [$warehouse, $cabinet]))
            ->assertOk()->assertSee(route('nursing-vouchers.show', $voucher), false);
    }

    public function test_cancelled_and_rejected_vouchers_without_allocations_do_not_appear(): void
    {
        [$warehouse, $cabinet, , , $requester] = $this->context();
        $this->voucher($warehouse, $requester, NursingSupplySourceType::WAREHOUSE, status: NursingVoucherStatus::CANCELLED);
        $this->voucher($warehouse, $requester, NursingSupplySourceType::CABINET, $cabinet, NursingVoucherStatus::REJECTED);

        $this->assertSame(0, app(InventoryKardexService::class)->forWarehouse($warehouse, [
            'movement_type' => InventoryKardexService::NURSING_VOUCHER_WAREHOUSE_OUT,
        ])->total());
        $this->assertSame(0, app(InventoryKardexService::class)->forCabinet($warehouse, $cabinet, [
            'movement_type' => InventoryKardexService::NURSING_VOUCHER_CABINET_OUT,
        ])->total());
    }

    public function test_context_and_type_filters_reject_cross_inventory_values_without_duplicates(): void
    {
        [$warehouse, $cabinet, $warehouseItem, , $requester, $supplier] = $this->context();
        $otherWarehouse = Warehouse::factory()->create();
        $otherProduct = Product::factory()->create();
        $otherItem = InventoryItem::factory()->forWarehouse($otherWarehouse)->for($otherProduct)->create();
        $voucher = $this->voucher($warehouse, $requester, NursingSupplySourceType::WAREHOUSE);
        $item = $voucher->items()->create(['product_id' => $warehouseItem->product_id, 'requested_quantity' => '1', 'supplied_quantity' => '1']);
        $this->fulfillment($voucher, $item->id, $supplier, '2026-09-01 12:00:00', [[$this->batch($warehouseItem, 'ONLY-LOT'), '1']]);

        $this->assertSame(1, app(InventoryKardexService::class)->forWarehouse($warehouse, [])->total());
        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('warehouses.kardex.index', [$warehouse, 'inventory_item_id' => $otherItem->id]))
            ->assertRedirect()->assertSessionHasErrors('inventory_item_id');
        $this->get(route('warehouses.cabinets.kardex.index', [$warehouse, $cabinet, 'movement_type' => InventoryKardexService::NURSING_VOUCHER_WAREHOUSE_OUT]))
            ->assertRedirect()->assertSessionHasErrors('movement_type');
    }

    private function context(): array
    {
        $warehouse = Warehouse::factory()->create();
        $cabinet = Cabinet::factory()->for($warehouse)->create();
        $product = Product::factory()->create();
        $warehouseItem = InventoryItem::factory()->forWarehouse($warehouse)->for($product)->create();
        $cabinetItem = InventoryItem::factory()->forCabinet($cabinet)->for($product)->create();
        $requester = User::factory()->create(['role' => UserRole::NURSE]);
        $requester->warehouses()->attach($warehouse);
        $supplier = User::factory()->warehouseManager()->create();
        $supplier->warehouses()->attach($warehouse);

        return [$warehouse, $cabinet, $warehouseItem, $cabinetItem, $requester, $supplier];
    }

    private function voucher(Warehouse $warehouse, User $requester, NursingSupplySourceType $source, ?Cabinet $cabinet = null, NursingVoucherStatus $status = NursingVoucherStatus::PARTIALLY_SUPPLIED): NursingVoucher
    {
        return NursingVoucher::query()->create([
            'requested_by' => $requester->id, 'warehouse_id' => $warehouse->id,
            'source_type' => $source, 'source_cabinet_id' => $cabinet?->id,
            'external_patient_id' => 'patient-k', 'external_hospitalization_id' => 'stay-k',
            'patient_name' => 'Paciente Kardex', 'external_room_id' => 'room-k', 'room_number' => '204',
            'status' => $status, 'requested_at' => '2026-09-01 09:00:00',
        ]);
    }

    private function batch(InventoryItem $item, string $lot, ?string $manufacturerLot = null, ?string $expiration = null): InventoryBatch
    {
        return InventoryBatch::query()->create([
            'inventory_item_id' => $item->id, 'internal_lot' => $lot,
            'manufacturer_lot' => $manufacturerLot, 'expiration_date' => $expiration,
            'received_quantity' => '20', 'available_quantity' => '10', 'unit_cost' => '1.0000',
        ]);
    }

    private function fulfillment(NursingVoucher $voucher, int $voucherItemId, User $supplier, string $suppliedAt, array $allocations): void
    {
        $fulfillment = $voucher->fulfillments()->create(['supplied_by' => $supplier->id, 'supplied_at' => $suppliedAt]);
        $quantity = collect($allocations)->reduce(fn (string $sum, array $allocation) => bcadd($sum, $allocation[1], 3), '0');
        $fulfillmentItem = $fulfillment->items()->create(['nursing_voucher_item_id' => $voucherItemId, 'quantity' => $quantity]);
        foreach ($allocations as [$batch, $allocated]) {
            $fulfillmentItem->allocations()->create(['inventory_batch_id' => $batch->id, 'quantity' => $allocated]);
        }
    }
}
