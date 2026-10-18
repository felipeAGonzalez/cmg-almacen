<?php

namespace Tests\Feature;

use App\Enums\AdministrationVoucherStatus;
use App\Enums\InventoryOutboundReason;
use App\Enums\ManualVoucherRequestingArea;
use App\Enums\NursingSupplySourceType;
use App\Enums\NursingVoucherStatus;
use App\Models\AdministrationVoucher;
use App\Models\Cabinet;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryOutbound;
use App\Models\InventoryTransfer;
use App\Models\NursingVoucher;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoucherPrintTest extends TestCase
{
    use RefreshDatabase;

    public function test_fulfilled_nursing_voucher_has_printable_document_and_pending_one_does_not(): void
    {
        $admin = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create(['name' => 'Almacén Central']);
        $voucher = $this->nursingVoucher($admin, $warehouse, NursingVoucherStatus::PARTIALLY_SUPPLIED);
        $product = Product::factory()->create(['name' => 'Paracetamol']);
        $voucher->items()->create(['product_id' => $product->id, 'requested_quantity' => '10', 'supplied_quantity' => '4']);
        $voucher->fulfillments()->create(['supplied_by' => $admin->id, 'supplied_at' => now()]);

        $this->actingAs($admin)->get(route('nursing-vouchers.show', $voucher))
            ->assertOk()
            ->assertSee(route('nursing-vouchers.print', $voucher))
            ->assertSee('Imprimir vale');

        $this->get(route('nursing-vouchers.print', $voucher))
            ->assertOk()
            ->assertSee('Centro Médico Guadalupano')
            ->assertSee('Paciente de impresión')
            ->assertSee('Habitación')
            ->assertSee('Paracetamol')
            ->assertDontSee('sidebar');

        $pending = $this->nursingVoucher($admin, $warehouse, NursingVoucherStatus::PENDING);
        $this->get(route('nursing-vouchers.print', $pending))->assertNotFound();
    }

    public function test_administration_voucher_is_printable_after_its_first_transfer(): void
    {
        $admin = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create(['name' => 'Almacén de pruebas']);
        $cabinet = Cabinet::factory()->for($warehouse)->create(['name' => 'Gabinete Norte']);
        $voucher = AdministrationVoucher::query()->create([
            'requested_by' => $admin->id,
            'warehouse_id' => $warehouse->id,
            'cabinet_id' => $cabinet->id,
            'status' => AdministrationVoucherStatus::PARTIALLY_SUPPLIED,
            'requested_at' => now(),
        ]);
        $product = Product::factory()->create(['name' => 'Material de curación']);
        $voucher->items()->create(['product_id' => $product->id, 'requested_quantity' => '8', 'supplied_quantity' => '3']);
        InventoryTransfer::query()->forceCreate([
            'administration_voucher_id' => $voucher->id,
            'warehouse_id' => $warehouse->id,
            'cabinet_id' => $cabinet->id,
            'transferred_by' => $admin->id,
            'transferred_at' => now(),
        ]);

        $this->actingAs($admin)->get(route('administration-vouchers.print', $voucher))
            ->assertOk()
            ->assertSee('Centro Médico Guadalupano')
            ->assertSee('Vale de Administración')
            ->assertSee('Almacén de pruebas')
            ->assertSee('Gabinete Norte')
            ->assertSee('Material de curación');
    }

    public function test_manual_patient_voucher_is_printable_and_access_remains_warehouse_scoped(): void
    {
        $warehouse = Warehouse::factory()->create(['name' => 'Almacén Manual']);
        $manager = User::factory()->warehouseManager()->create();
        $manager->warehouses()->attach($warehouse);
        $outbound = $warehouse->inventoryOutbounds()->create([
            'reason' => InventoryOutboundReason::PATIENT_CARE,
            'patient_name' => 'Paciente en papel',
            'room_number' => '205',
            'requesting_area' => 'Enfermería',
            'processed_by' => $manager->id,
            'processed_at' => now(),
            'source_type' => InventoryOutbound::SOURCE_MANUAL,
        ]);
        $product = Product::factory()->create(['name' => 'Medicamento impreso']);
        $inventoryItem = InventoryItem::factory()->forWarehouse($warehouse)->for($product)->create();
        $batch = InventoryBatch::query()->create([
            'inventory_item_id' => $inventoryItem->id,
            'internal_lot' => 'PRINT-001',
            'received_quantity' => '5.000',
            'available_quantity' => '3.000',
            'unit_cost' => '10.0000',
        ]);
        $outboundItem = $outbound->items()->create([
            'inventory_item_id' => $inventoryItem->id,
            'requested_quantity' => '2.000',
        ]);
        $outboundItem->allocations()->create([
            'inventory_batch_id' => $batch->id,
            'quantity' => '2.000',
        ]);

        $this->actingAs($manager)->get(route('warehouses.outbounds.print', [$warehouse, $outbound]))
            ->assertOk()
            ->assertSee('Centro Médico Guadalupano')
            ->assertSee('Solicitud de medicamentos')
            ->assertSee('Paciente en papel')
            ->assertSee('205')
            ->assertSee('Medicamento impreso')
            ->assertSee('PRINT-001');

        $foreignManager = User::factory()->warehouseManager()->create();
        $this->actingAs($foreignManager)
            ->get(route('warehouses.outbounds.print', [$warehouse, $outbound]))
            ->assertForbidden();
    }

    public function test_non_patient_manual_outbound_is_not_exposed_as_a_voucher(): void
    {
        $admin = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create();
        $outbound = $warehouse->inventoryOutbounds()->create([
            'reason' => InventoryOutboundReason::INTERNAL_CONSUMPTION,
            'processed_by' => $admin->id,
            'processed_at' => now(),
            'source_type' => InventoryOutbound::SOURCE_MANUAL,
        ]);

        $this->actingAs($admin)
            ->get(route('warehouses.outbounds.print', [$warehouse, $outbound]))
            ->assertNotFound();
    }

    public function test_pamedic_manual_voucher_uses_its_own_letterhead_and_logo_path(): void
    {
        $admin = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create();
        $outbound = $warehouse->inventoryOutbounds()->create([
            'reason' => InventoryOutboundReason::PATIENT_CARE,
            'patient_name' => 'Paciente Pamedic',
            'requesting_area' => ManualVoucherRequestingArea::PAMEDIC->value,
            'processed_by' => $admin->id,
            'processed_at' => now(),
            'source_type' => InventoryOutbound::SOURCE_MANUAL,
        ]);

        $this->actingAs($admin)
            ->get(route('warehouses.outbounds.print', [$warehouse, $outbound]))
            ->assertOk()
            ->assertSee('Corporación Pamedic')
            ->assertSee('Vale de medicamentos e insumos')
            ->assertSee('Espacio para')
            ->assertDontSee('Centro Médico Guadalupano');
    }

    private function nursingVoucher(
        User $requester,
        Warehouse $warehouse,
        NursingVoucherStatus $status,
    ): NursingVoucher {
        return NursingVoucher::query()->create([
            'requested_by' => $requester->id,
            'warehouse_id' => $warehouse->id,
            'source_type' => NursingSupplySourceType::WAREHOUSE,
            'external_patient_id' => 'patient-print',
            'external_hospitalization_id' => 'stay-print',
            'patient_name' => 'Paciente de impresión',
            'external_room_id' => 'room-print',
            'room_number' => '204',
            'status' => $status,
            'requested_at' => now(),
        ]);
    }
}
