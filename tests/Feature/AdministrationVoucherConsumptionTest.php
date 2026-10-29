<?php

namespace Tests\Feature;

use App\Enums\InventoryOutboundReason;
use App\Enums\NursingSupplySourceType;
use App\Enums\NursingVoucherReturnStatus;
use App\Enums\NursingVoucherStatus;
use App\Models\AdministrationVoucher;
use App\Models\AdministrationVoucherConsumption;
use App\Models\Cabinet;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryOutbound;
use App\Models\InventoryOutboundAllocation;
use App\Models\NursingVoucher;
use App\Models\NursingVoucherAllocation;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\AdministrationVoucherConsumptionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdministrationVoucherConsumptionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_previous_cabinet_nursing_consumption_can_generate_a_traceable_voucher(): void
    {
        CarbonImmutable::setTestNow('2026-10-21 09:00:00');
        [$warehouse, $cabinet, $product, $cabinetItem] = $this->context();
        $admin = User::factory()->administrator()->create();
        $allocation = $this->nursingAllocation($warehouse, $cabinet, $product, $cabinetItem, '6.000', '2026-10-20 18:00:00', $admin);

        $this->actingAs($admin)->get(route('administration-vouchers.index'))
            ->assertOk()
            ->assertSee('Consumo de gabinetes pendiente de reposición')
            ->assertSee('Generar vale')
            ->assertSee($product->name)
            ->assertSee('6');

        $response = $this->post(route('administration-vouchers.consumptions.store'), [
            'cabinet_id' => $cabinet->id,
            'consumption_date' => '2026-10-20',
        ]);

        $voucher = AdministrationVoucher::query()
            ->where('warehouse_id', $warehouse->id)
            ->firstOrFail();
        $response->assertRedirect(route('administration-vouchers.show', $voucher));
        $this->assertSame('6.000', $voucher->items()->firstOrFail()->requested_quantity);
        $this->assertDatabaseHas('administration_voucher_consumptions', [
            'administration_voucher_id' => $voucher->id,
            'source_type' => AdministrationVoucherConsumption::NURSING_ALLOCATION,
            'source_id' => $allocation->id,
            'quantity' => '6.000',
        ]);
    }

    public function test_nursing_and_manual_consumptions_are_aggregated_by_product(): void
    {
        CarbonImmutable::setTestNow('2026-10-21 09:00:00');
        [$warehouse, $cabinet, $product, $cabinetItem] = $this->context();
        $admin = User::factory()->administrator()->create();
        $this->nursingAllocation($warehouse, $cabinet, $product, $cabinetItem, '2.250', '2026-10-20 10:00:00', $admin);
        $this->manualAllocation($warehouse, $cabinet, $cabinetItem, '3.750', '2026-10-20 12:00:00', $admin);

        $voucher = app(AdministrationVoucherConsumptionService::class)
            ->generate($admin, $cabinet, '2026-10-20');

        $this->assertCount(1, $voucher->items);
        $this->assertSame('6.000', $voucher->items->first()->requested_quantity);
        $this->assertCount(2, $voucher->consumptions);
    }

    public function test_received_returns_are_deducted_from_the_replenishment_quantity(): void
    {
        CarbonImmutable::setTestNow('2026-10-21 09:00:00');
        [$warehouse, $cabinet, $product, $cabinetItem] = $this->context();
        $admin = User::factory()->administrator()->create();
        $allocation = $this->nursingAllocation($warehouse, $cabinet, $product, $cabinetItem, '5.000', '2026-10-20 10:00:00', $admin);
        $voucher = $allocation->fulfillmentItem->fulfillment->voucher;
        $return = $voucher->returns()->create([
            'requested_by' => $admin->id,
            'received_by' => $admin->id,
            'status' => NursingVoucherReturnStatus::RECEIVED,
            'requested_at' => now(),
            'received_at' => now(),
        ]);
        $return->items()->create([
            'nursing_voucher_allocation_id' => $allocation->id,
            'quantity' => '2.000',
        ]);

        $generated = app(AdministrationVoucherConsumptionService::class)
            ->generate($admin, $cabinet, '2026-10-20');

        $this->assertSame('3.000', $generated->items->first()->requested_quantity);
        $this->assertSame('3.000', $generated->consumptions->first()->quantity);
    }

    public function test_consumption_cannot_generate_two_administration_vouchers(): void
    {
        CarbonImmutable::setTestNow('2026-10-21 09:00:00');
        [$warehouse, $cabinet, $product, $cabinetItem] = $this->context();
        $admin = User::factory()->administrator()->create();
        $this->manualAllocation($warehouse, $cabinet, $cabinetItem, '2.000', '2026-10-20 12:00:00', $admin);
        $service = app(AdministrationVoucherConsumptionService::class);
        $service->generate($admin, $cabinet, '2026-10-20');

        $this->expectException(ValidationException::class);
        $service->generate($admin, $cabinet, '2026-10-20');
    }

    public function test_current_day_consumption_is_not_available_until_the_next_day(): void
    {
        CarbonImmutable::setTestNow('2026-10-21 09:00:00');
        [$warehouse, $cabinet, $product, $cabinetItem] = $this->context();
        $admin = User::factory()->administrator()->create();
        $this->manualAllocation($warehouse, $cabinet, $cabinetItem, '2.000', '2026-10-21 08:00:00', $admin);

        $this->actingAs($admin)->get(route('administration-vouchers.index'))
            ->assertOk()
            ->assertDontSee('Consumo de gabinetes pendiente de reposición');

        $this->post(route('administration-vouchers.consumptions.store'), [
            'cabinet_id' => $cabinet->id,
            'consumption_date' => '2026-10-21',
        ])->assertSessionHasErrors('consumption_date');
    }

    public function test_consumption_without_warehouse_product_configuration_is_shown_but_cannot_be_generated(): void
    {
        CarbonImmutable::setTestNow('2026-10-21 09:00:00');
        $warehouse = Warehouse::factory()->create();
        $cabinet = Cabinet::factory()->for($warehouse)->create();
        $product = Product::factory()->create();
        $cabinetItem = InventoryItem::factory()->forCabinet($cabinet)->for($product)->create();
        $admin = User::factory()->administrator()->create();
        $this->manualAllocation($warehouse, $cabinet, $cabinetItem, '2.000', '2026-10-20 12:00:00', $admin);

        $this->actingAs($admin)->get(route('administration-vouchers.index'))
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee('Falta configurar un producto en el almacén')
            ->assertDontSee('Generar vale');

        $this->actingAs($admin)->post(route('administration-vouchers.consumptions.store'), [
            'cabinet_id' => $cabinet->id,
            'consumption_date' => '2026-10-20',
        ])->assertSessionHasErrors('items');

        $this->assertDatabaseCount('administration_vouchers', 0);
        $this->assertDatabaseCount('administration_voucher_consumptions', 0);
    }

    public function test_unassigned_manager_cannot_generate_or_see_foreign_consumption(): void
    {
        CarbonImmutable::setTestNow('2026-10-21 09:00:00');
        [$warehouse, $cabinet, $product, $cabinetItem] = $this->context();
        $admin = User::factory()->administrator()->create();
        $manager = User::factory()->warehouseManager()->create();
        $this->manualAllocation($warehouse, $cabinet, $cabinetItem, '2.000', '2026-10-20 12:00:00', $admin);

        $this->actingAs($manager)->post(route('administration-vouchers.consumptions.store'), [
            'cabinet_id' => $cabinet->id,
            'consumption_date' => '2026-10-20',
        ])->assertForbidden();
    }

    /** @return array{Warehouse, Cabinet, Product, InventoryItem} */
    private function context(): array
    {
        $warehouse = Warehouse::factory()->create();
        $cabinet = Cabinet::factory()->for($warehouse)->create();
        $product = Product::factory()->create();
        InventoryItem::factory()->forWarehouse($warehouse)->for($product)->create();
        $cabinetItem = InventoryItem::factory()->forCabinet($cabinet)->for($product)->create();

        return [$warehouse, $cabinet, $product, $cabinetItem];
    }

    private function nursingAllocation(
        Warehouse $warehouse,
        Cabinet $cabinet,
        Product $product,
        InventoryItem $cabinetItem,
        string $quantity,
        string $suppliedAt,
        User $user,
    ): NursingVoucherAllocation {
        $voucher = NursingVoucher::query()->create([
            'requested_by' => $user->id,
            'warehouse_id' => $warehouse->id,
            'source_type' => NursingSupplySourceType::CABINET,
            'source_cabinet_id' => $cabinet->id,
            'external_patient_id' => 'patient-consumption',
            'external_hospitalization_id' => 'stay-consumption',
            'patient_name' => 'Paciente Consumo',
            'external_room_id' => 'room-consumption',
            'room_number' => '101',
            'status' => NursingVoucherStatus::SUPPLIED,
            'requested_at' => $suppliedAt,
        ]);
        $voucherItem = $voucher->items()->create([
            'product_id' => $product->id,
            'requested_quantity' => $quantity,
            'supplied_quantity' => $quantity,
        ]);
        $fulfillment = $voucher->fulfillments()->create([
            'supplied_by' => $user->id,
            'supplied_at' => $suppliedAt,
        ]);
        $fulfillmentItem = $fulfillment->items()->create([
            'nursing_voucher_item_id' => $voucherItem->id,
            'quantity' => $quantity,
        ]);
        $batch = $this->batch($cabinetItem);

        return $fulfillmentItem->allocations()->create([
            'inventory_batch_id' => $batch->id,
            'quantity' => $quantity,
        ]);
    }

    private function manualAllocation(
        Warehouse $warehouse,
        Cabinet $cabinet,
        InventoryItem $cabinetItem,
        string $quantity,
        string $processedAt,
        User $user,
    ): InventoryOutboundAllocation {
        $outbound = $warehouse->inventoryOutbounds()->create([
            'source_cabinet_id' => $cabinet->id,
            'reason' => InventoryOutboundReason::INTERNAL_CONSUMPTION,
            'processed_by' => $user->id,
            'processed_at' => $processedAt,
            'source_type' => InventoryOutbound::SOURCE_MANUAL,
        ]);
        $item = $outbound->items()->create([
            'inventory_item_id' => $cabinetItem->id,
            'requested_quantity' => $quantity,
        ]);
        $batch = $this->batch($cabinetItem);

        return $item->allocations()->create([
            'inventory_batch_id' => $batch->id,
            'quantity' => $quantity,
        ]);
    }

    private function batch(InventoryItem $inventoryItem): InventoryBatch
    {
        return InventoryBatch::query()->create([
            'inventory_item_id' => $inventoryItem->id,
            'internal_lot' => 'CONSUMPTION-'.$inventoryItem->id.'-'.uniqid(),
            'received_quantity' => '100.000',
            'available_quantity' => '50.000',
            'unit_cost' => '1.00000',
        ]);
    }
}
