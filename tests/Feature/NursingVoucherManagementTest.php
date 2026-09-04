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
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\NursingVoucherFulfillmentService;
use App\Services\NursingVoucherService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class NursingVoucherManagementTest extends TestCase
{
    use RefreshDatabase;

    private int $lotSequence = 0;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_nurse_creates_warehouse_voucher_with_revalidated_snapshot_and_frozen_source(): void
    {
        [$nurse, $warehouse, $product] = $this->creationContext(open: true);
        $this->fakeActiveHospitalization();

        $response = $this->actingAs($nurse)
            ->withSession(['hospital_context' => $this->hospitalContext()])
            ->post(route('nursing-vouchers.store'), [
                'notes' => '  Solicitud clínica  ',
                'items' => [['product_id' => $product->id, 'quantity' => '10.500']],
                'patient_name' => 'Manipulado',
                'warehouse_id' => 999999,
                'source_type' => 'cabinet',
            ]);

        $voucher = NursingVoucher::firstOrFail();
        $response->assertRedirect(route('nursing-vouchers.show', $voucher));
        $this->assertSame($nurse->id, $voucher->requested_by);
        $this->assertSame($warehouse->id, $voucher->warehouse_id);
        $this->assertSame(NursingSupplySourceType::WAREHOUSE, $voucher->source_type);
        $this->assertNull($voucher->source_cabinet_id);
        $this->assertSame('Paciente validado', $voucher->patient_name);
        $this->assertSame('patient-1', $voucher->external_patient_id);
        $this->assertSame('stay-1', $voucher->external_hospitalization_id);
        $this->assertSame('room-1', $voucher->external_room_id);
        $this->assertSame('204', $voucher->room_number);
        $this->assertSame('Solicitud clínica', $voucher->notes);
        $this->assertSame(NursingVoucherStatus::PENDING, $voucher->status);
        $this->assertSame('10.500', $voucher->items->first()->requested_quantity);
        $this->assertSame('0.000', $voucher->items->first()->supplied_quantity);
        $this->assertSame('10.500', $voucher->items->first()->pendingQuantity());
    }

    public function test_context_is_required_and_inactive_or_mismatched_hospitalization_fails(): void
    {
        [$nurse, , $product] = $this->creationContext(open: true);

        $this->actingAs($nurse)->post(route('nursing-vouchers.store'), [
            'items' => [['product_id' => $product->id, 'quantity' => '1']],
        ])->assertSessionHasErrors('hospital_context');

        Http::fake(['*' => Http::response(['data' => []])]);
        $this->withSession(['hospital_context' => $this->hospitalContext()])
            ->post(route('nursing-vouchers.store'), [
                'items' => [['product_id' => $product->id, 'quantity' => '1']],
            ])->assertSessionHasErrors([
                'hospital_context' => 'La hospitalización ya no se encuentra activa.',
            ]);

        $this->assertDatabaseCount('nursing_vouchers', 0);
    }

    public function test_closed_and_rest_day_sources_use_default_cabinet_and_missing_default_fails(): void
    {
        foreach ([
            '2026-09-07 18:00:00',
            '2026-09-06 10:00:00',
        ] as $dateTime) {
            [$nurse, $warehouse, $product, $cabinet] = $this->creationContext(open: false, now: $dateTime);
            $this->fakeActiveHospitalization();

            $voucher = app(NursingVoucherService::class)->createForNurse(
                $nurse,
                [['product_id' => $product->id, 'quantity' => '1']],
                null,
                $this->hospitalContext(),
            );

            $this->assertSame(NursingSupplySourceType::CABINET, $voucher->source_type);
            $this->assertSame($cabinet->id, $voucher->source_cabinet_id);
            $this->assertSame($warehouse->id, $voucher->warehouse_id);
        }

        [$nurse, $warehouse, $product] = $this->creationContext(open: false, now: '2026-09-07 18:00:00');
        $warehouse->update(['default_nursing_cabinet_id' => null]);
        $this->fakeActiveHospitalization();

        $this->expectExceptionMessage('El almacén no tiene configurado un gabinete predeterminado de Enfermería.');
        app(NursingVoucherService::class)->createForNurse(
            $nurse,
            [['product_id' => $product->id, 'quantity' => '1']],
            null,
            $this->hospitalContext(),
        );
    }

    public function test_nurse_without_warehouse_and_unconfigured_or_duplicate_products_fail(): void
    {
        $nurse = User::factory()->create(['role' => UserRole::NURSE]);
        $product = Product::factory()->create();
        $this->fakeActiveHospitalization();
        Carbon::setTestNow('2026-09-07 10:00:00');

        try {
            app(NursingVoucherService::class)->createForNurse(
                $nurse,
                [['product_id' => $product->id, 'quantity' => '1']],
                null,
                $this->hospitalContext(),
            );
            $this->fail('Nurse without warehouse should fail.');
        } catch (\Throwable $exception) {
            $this->assertSame('La enfermera no tiene un almacén asignado.', $exception->getMessage());
        }

        [$configuredNurse] = $this->creationContext(open: true);
        $this->actingAs($configuredNurse)
            ->withSession(['hospital_context' => $this->hospitalContext()])
            ->post(route('nursing-vouchers.store'), [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => '1'],
                    ['product_id' => $product->id, 'quantity' => '2'],
                ],
            ])->assertSessionHasErrors('items.1.product_id');
    }

    public function test_insufficient_stock_does_not_prevent_creation(): void
    {
        [$nurse, , $product] = $this->creationContext(open: true);
        $this->fakeActiveHospitalization();

        $voucher = app(NursingVoucherService::class)->createForNurse(
            $nurse,
            [['product_id' => $product->id, 'quantity' => '999']],
            null,
            $this->hospitalContext(),
        );

        $this->assertSame(NursingVoucherStatus::PENDING, $voucher->status);
        $this->assertSame('999.000', $voucher->items->first()->pendingQuantity());
    }

    public function test_partial_then_second_fulfillment_completes_and_audits_batches(): void
    {
        [$voucher, $manager, $inventoryItem] = $this->warehouseVoucher('10');
        $firstBatch = $this->rootBatch($inventoryItem, '4', '2026-10-01');
        $secondBatch = $this->rootBatch($inventoryItem, '10', '2026-11-01');
        $service = app(NursingVoucherFulfillmentService::class);

        $first = $service->fulfill($voucher, $manager, [
            ['voucher_item_id' => $voucher->items->first()->id, 'quantity' => '6'],
        ], 'Primera entrega');

        $voucher->refresh()->load('items');
        $this->assertSame(NursingVoucherStatus::PARTIALLY_SUPPLIED, $voucher->status);
        $this->assertSame('6.000', $voucher->items->first()->supplied_quantity);
        $this->assertSame('4.000', $voucher->items->first()->pendingQuantity());
        $this->assertCount(2, $first->items->first()->allocations);
        $this->assertSame('0.000', $firstBatch->fresh()->available_quantity);
        $this->assertSame('8.000', $secondBatch->fresh()->available_quantity);
        $this->assertSame('4.000', $firstBatch->fresh()->received_quantity);

        $second = $service->fulfill($voucher, $manager, [
            ['voucher_item_id' => $voucher->items->first()->id, 'quantity' => '4'],
        ]);

        $voucher->refresh()->load('items');
        $this->assertSame(NursingVoucherStatus::SUPPLIED, $voucher->status);
        $this->assertNotNull($voucher->completed_at);
        $this->assertSame('0.000', $voucher->items->first()->pendingQuantity());
        $this->assertNotSame($first->id, $second->id);
        $this->assertDatabaseCount('nursing_voucher_fulfillments', 2);
    }

    public function test_fifo_expired_exclusion_and_insufficient_attempt_roll_back(): void
    {
        [$voucher, $manager, $inventoryItem] = $this->warehouseVoucher('8');
        $expired = $this->rootBatch($inventoryItem, '50', '2026-09-01');
        $oldest = $this->rootBatch($inventoryItem, '3', null, '2026-08-01');
        $newer = $this->rootBatch($inventoryItem, '3', null, '2026-08-02');

        try {
            app(NursingVoucherFulfillmentService::class)->fulfill($voucher, $manager, [
                ['voucher_item_id' => $voucher->items->first()->id, 'quantity' => '8'],
            ]);
            $this->fail('Insufficient usable stock should fail.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'No hay existencia utilizable suficiente para surtir este producto.',
                $exception->errors()['items.0.quantity'][0],
            );
        }

        $this->assertSame('50.000', $expired->fresh()->available_quantity);
        $this->assertSame('3.000', $oldest->fresh()->available_quantity);
        $this->assertSame('3.000', $newer->fresh()->available_quantity);
        $this->assertDatabaseCount('nursing_voucher_fulfillments', 0);

        app(NursingVoucherFulfillmentService::class)->fulfill($voucher, $manager, [
            ['voucher_item_id' => $voucher->items->first()->id, 'quantity' => '4'],
        ]);
        $this->assertSame('0.000', $oldest->fresh()->available_quantity);
        $this->assertSame('2.000', $newer->fresh()->available_quantity);
    }

    public function test_over_supply_and_terminal_statuses_cannot_be_fulfilled(): void
    {
        [$voucher, $manager, $inventoryItem] = $this->warehouseVoucher('2');
        $this->rootBatch($inventoryItem, '10');

        $this->expectValidation(
            fn () => app(NursingVoucherFulfillmentService::class)->fulfill($voucher, $manager, [
                ['voucher_item_id' => $voucher->items->first()->id, 'quantity' => '3'],
            ]),
            'La cantidad a surtir no puede superar la cantidad pendiente.',
        );

        foreach ([NursingVoucherStatus::CANCELLED, NursingVoucherStatus::REJECTED, NursingVoucherStatus::SUPPLIED] as $status) {
            $voucher->update(['status' => $status]);
            $this->expectValidation(
                fn () => app(NursingVoucherFulfillmentService::class)->fulfill($voucher->fresh(), $manager, [
                    ['voucher_item_id' => $voucher->items->first()->id, 'quantity' => '1'],
                ]),
                'Este vale ya no puede surtirse.',
            );
        }
    }

    public function test_warehouse_and_cabinet_fulfillment_authorization_is_scoped(): void
    {
        [$warehouseVoucher, $manager, $warehouseItem] = $this->warehouseVoucher('1');
        $this->rootBatch($warehouseItem, '5');
        $foreignManager = User::factory()->create(['role' => UserRole::WAREHOUSE_MANAGER]);
        $foreignManager->warehouses()->attach(Warehouse::factory()->create());
        $nurse = User::factory()->create(['role' => UserRole::NURSE]);
        $nurse->warehouses()->attach($warehouseVoucher->warehouse);

        $this->assertTrue($manager->can('fulfill', $warehouseVoucher));
        $this->assertFalse($foreignManager->can('fulfill', $warehouseVoucher));
        $this->assertFalse($nurse->can('fulfill', $warehouseVoucher));
        $this->assertTrue(User::factory()->administrator()->create()->can('fulfill', $warehouseVoucher));
        $this->assertTrue(User::factory()->create(['role' => UserRole::ROOT])->can('fulfill', $warehouseVoucher));

        [$cabinetVoucher, $cabinetNurse, $cabinetItem] = $this->cabinetVoucher('1');
        $this->cabinetBatch($cabinetItem, '5');
        $cabinetManager = User::factory()->create(['role' => UserRole::WAREHOUSE_MANAGER]);
        $cabinetManager->warehouses()->attach($cabinetVoucher->warehouse);

        $this->assertTrue($cabinetNurse->can('fulfill', $cabinetVoucher));
        $this->assertFalse($cabinetManager->can('fulfill', $cabinetVoucher));
        $this->assertFalse($nurse->can('fulfill', $cabinetVoucher));
        $this->assertTrue(User::factory()->administrator()->create()->can('fulfill', $cabinetVoucher));
    }

    public function test_pending_reject_and_cancel_are_audited_without_stock_changes(): void
    {
        [$voucher, $manager, $inventoryItem] = $this->warehouseVoucher('2');
        $batch = $this->rootBatch($inventoryItem, '5');
        $service = app(NursingVoucherService::class);

        $service->reject($voucher, $manager, 'No autorizado');
        $voucher->refresh();
        $this->assertSame(NursingVoucherStatus::REJECTED, $voucher->status);
        $this->assertSame($manager->id, $voucher->rejected_by);
        $this->assertNotNull($voucher->rejected_at);
        $this->assertSame('No autorizado', $voucher->rejection_reason);
        $this->assertSame('5.000', $batch->fresh()->available_quantity);

        [$cancelVoucher, , $cancelItem] = $this->warehouseVoucher('2');
        $this->rootBatch($cancelItem, '5');
        $service->cancel($cancelVoucher, $cancelVoucher->requester);
        $cancelVoucher->refresh();
        $this->assertSame(NursingVoucherStatus::CANCELLED, $cancelVoucher->status);
        $this->assertSame($cancelVoucher->requested_by, $cancelVoucher->cancelled_by);
        $this->assertNotNull($cancelVoucher->cancelled_at);
    }

    public function test_partially_supplied_voucher_cannot_be_rejected_or_cancelled(): void
    {
        [$voucher, $manager, $inventoryItem] = $this->warehouseVoucher('2');
        $this->rootBatch($inventoryItem, '5');
        app(NursingVoucherFulfillmentService::class)->fulfill($voucher, $manager, [
            ['voucher_item_id' => $voucher->items->first()->id, 'quantity' => '1'],
        ]);

        $this->expectValidation(
            fn () => app(NursingVoucherService::class)->reject($voucher->fresh(), $manager, 'Tarde'),
            'Sólo se puede rechazar un vale pendiente.',
        );
        $this->expectValidation(
            fn () => app(NursingVoucherService::class)->cancel($voucher->fresh(), $voucher->requester),
            'Sólo se puede cancelar un vale pendiente.',
        );
    }

    private function creationContext(bool $open, string $now = '2026-09-07 10:00:00'): array
    {
        Carbon::setTestNow($now);
        $warehouse = Warehouse::factory()->create();
        $cabinet = Cabinet::factory()->for($warehouse)->create();
        $warehouse->update(['default_nursing_cabinet_id' => $cabinet->id]);
        $nurse = User::factory()->create(['role' => UserRole::NURSE]);
        $nurse->warehouses()->attach($warehouse);
        $product = Product::factory()->create();
        InventoryItem::factory()->forWarehouse($warehouse)->for($product)->create();
        InventoryItem::factory()->forCabinet($cabinet)->for($product)->create();

        if (! $open) {
            Carbon::setTestNow($now);
        }

        return [$nurse, $warehouse, $product, $cabinet];
    }

    private function warehouseVoucher(string $quantity): array
    {
        [$nurse, $warehouse, $product] = $this->creationContext(open: true);
        $this->fakeActiveHospitalization();
        $voucher = app(NursingVoucherService::class)->createForNurse(
            $nurse,
            [['product_id' => $product->id, 'quantity' => $quantity]],
            null,
            $this->hospitalContext(),
        );
        $manager = User::factory()->create(['role' => UserRole::WAREHOUSE_MANAGER]);
        $manager->warehouses()->attach($warehouse);

        return [$voucher, $manager, $warehouse->inventoryItems()->where('product_id', $product->id)->firstOrFail()];
    }

    private function cabinetVoucher(string $quantity): array
    {
        [$nurse, , $product, $cabinet] = $this->creationContext(open: false, now: '2026-09-07 18:00:00');
        $this->fakeActiveHospitalization();
        $voucher = app(NursingVoucherService::class)->createForNurse(
            $nurse,
            [['product_id' => $product->id, 'quantity' => $quantity]],
            null,
            $this->hospitalContext(),
        );

        return [$voucher, $nurse, $cabinet->inventoryItems()->where('product_id', $product->id)->firstOrFail()];
    }

    private function rootBatch(
        InventoryItem $item,
        string $quantity,
        ?string $expiration = '2027-01-01',
        ?string $createdAt = null,
    ): InventoryBatch {
        $warehouse = $item->stockable;
        $entry = $warehouse->entries()->create([
            'supplier_id' => Supplier::factory()->for($warehouse)->create()->id,
            'invoice_number' => 'VOUCHER-'.(++$this->lotSequence),
            'invoice_date' => today(),
        ]);
        $entryItem = $entry->items()->create([
            'inventory_item_id' => $item->id,
            'quantity' => $quantity,
            'unit_cost' => '1.0000',
            'expiration_date' => $expiration,
        ]);
        $batch = $entryItem->batch()->create([
            'inventory_item_id' => $item->id,
            'internal_lot' => 'NV-LOT-'.$this->lotSequence,
            'expiration_date' => $expiration,
            'received_quantity' => $quantity,
            'available_quantity' => $quantity,
            'unit_cost' => '1.0000',
        ]);

        if ($createdAt) {
            $batch->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
        }

        return $batch;
    }

    private function cabinetBatch(InventoryItem $cabinetItem, string $quantity): InventoryBatch
    {
        $warehouse = $cabinetItem->stockable->warehouse;
        $warehouseItem = $warehouse->inventoryItems()->where('product_id', $cabinetItem->product_id)->first()
            ?? InventoryItem::factory()->forWarehouse($warehouse)->for($cabinetItem->product)->create();
        $root = $this->rootBatch($warehouseItem, $quantity);

        return InventoryBatch::query()->create([
            'inventory_item_id' => $cabinetItem->id,
            'entry_item_id' => null,
            'source_batch_id' => $root->id,
            'internal_lot' => 'NV-CABINET-'.$root->id,
            'expiration_date' => $root->expiration_date,
            'received_quantity' => $quantity,
            'available_quantity' => $quantity,
            'unit_cost' => $root->unit_cost,
        ]);
    }

    private function fakeActiveHospitalization(): void
    {
        Http::fake(['*' => Http::response(['data' => [[
            'patient_id' => 'patient-1',
            'hospitalization_id' => 'stay-1',
            'patient_name' => 'Paciente validado',
            'room_id' => 'room-1',
            'room_number' => '204',
        ]]])]);
    }

    private function hospitalContext(): array
    {
        return [
            'patient_id' => 'patient-1',
            'hospitalization_id' => 'stay-1',
            'room_id' => 'room-1',
            'room_number' => '204',
        ];
    }

    private function expectValidation(callable $callback, string $message): void
    {
        try {
            $callback();
            $this->fail('Expected validation exception.');
        } catch (ValidationException $exception) {
            $this->assertContains($message, collect($exception->errors())->flatten()->all());
        }
    }
}
