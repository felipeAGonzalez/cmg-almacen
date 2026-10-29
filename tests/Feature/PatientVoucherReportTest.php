<?php

namespace Tests\Feature;

use App\Enums\InventoryOutboundReason;
use App\Enums\NursingSupplySourceType;
use App\Enums\NursingVoucherStatus;
use App\Enums\UserRole;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryOutbound;
use App\Models\NursingVoucher;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class PatientVoucherReportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_report_is_available_only_to_administration(): void
    {
        $this->get(route('reports.patient-vouchers.index'))->assertRedirect(route('login'));

        foreach ([User::factory()->warehouseManager()->create(), User::factory()->nurse()->create()] as $user) {
            $this->actingAs($user)->get(route('reports.patient-vouchers.index'))->assertForbidden();
        }

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('reports.patient-vouchers.index'))->assertOk()->assertSee('Concentrado de vales por paciente');
        $this->actingAs(User::factory()->create(['role' => UserRole::ROOT]))
            ->get(route('reports.patient-vouchers.index'))->assertOk();
    }

    public function test_index_defaults_to_today_and_groups_nursing_and_manual_vouchers_by_patient(): void
    {
        Carbon::setTestNow('2026-10-15 12:00:00');
        $warehouse = Warehouse::factory()->create();
        $user = User::factory()->administrator()->create();
        $this->nursingVoucher($warehouse, $user, 'Paciente Unificado', now()->subHour());
        $this->manualVoucher($warehouse, $user, ' paciente unificado ', now());
        $this->manualVoucher($warehouse, $user, 'Paciente de ayer', now()->subDay());

        $this->actingAs($user)->get(route('reports.patient-vouchers.index'))
            ->assertOk()->assertSee('Paciente Unificado')->assertDontSee('Paciente de ayer')
            ->assertSeeInOrder(['Paciente Unificado', '1', '1', '2'])
            ->assertSee('2026-10-15', false);
    }

    public function test_date_filter_and_patient_detail_show_separate_vouchers_and_net_consolidation(): void
    {
        Carbon::setTestNow('2026-10-15 12:00:00');
        $warehouse = Warehouse::factory()->create(['name' => 'Almacén reporte']);
        $user = User::factory()->administrator()->create();
        $product = Product::factory()->create(['name' => 'Producto consolidado']);
        $inventoryItem = InventoryItem::factory()->forWarehouse($warehouse)->for($product)->create();
        $nursing = $this->nursingVoucher($warehouse, $user, 'Paciente Consolidado', now()->subHour());
        $nursing->items()->create([
            'product_id' => $product->id,
            'requested_quantity' => '3.000',
            'supplied_quantity' => '3.000',
        ]);
        $manual = $this->manualVoucher($warehouse, $user, 'Paciente Consolidado', now());
        $manualItem = $manual->items()->create([
            'inventory_item_id' => $inventoryItem->id,
            'requested_quantity' => '2.000',
        ]);
        $batch = InventoryBatch::create([
            'inventory_item_id' => $inventoryItem->id,
            'entry_item_id' => null,
            'source_batch_id' => null,
            'internal_lot' => 'REPORT-LOT',
            'manufacturer_lot' => null,
            'expiration_date' => null,
            'received_quantity' => '5.000',
            'available_quantity' => '3.000',
            'unit_cost' => '10.0000',
        ]);
        $allocation = $manualItem->allocations()->create([
            'inventory_batch_id' => $batch->id,
            'quantity' => '2.000',
        ]);
        $return = $manual->returns()->create([
            'warehouse_id' => $warehouse->id,
            'received_by' => $user->id,
            'received_at' => now(),
        ]);
        $return->items()->create([
            'inventory_outbound_allocation_id' => $allocation->id,
            'quantity' => '1.000',
        ]);

        $filters = ['date_from' => '2026-10-15', 'date_to' => '2026-10-15'];
        $this->actingAs($user)->get(route('reports.patient-vouchers.show', [
            'patient' => Crypt::encryptString('paciente consolidado'),
            ...$filters,
        ]))->assertOk()
            ->assertSee('Vales consolidados')->assertSee('Vales por separado')
            ->assertSee('id="patient-vouchers-accordion"', false)
            ->assertSee('id="consolidated-vouchers" class="accordion-collapse collapse show"', false)
            ->assertSee('id="individual-vouchers" class="accordion-collapse collapse"', false)
            ->assertSee('Vale de Enfermería #'.$nursing->id)
            ->assertSee('Vale manual #'.$manual->id)
            ->assertSee('Producto consolidado')
            ->assertSeeInOrder(['5', '5', '1', '4'])
            ->assertDontSee('Costo');

        $this->get(route('reports.patient-vouchers.index', [
            'date_from' => '2026-10-14',
            'date_to' => '2026-10-14',
        ]))->assertOk()->assertDontSee('Paciente Consolidado');
    }

    public function test_index_filters_by_patient_name_and_room_number(): void
    {
        Carbon::setTestNow('2026-10-15 12:00:00');
        $warehouse = Warehouse::factory()->create();
        $user = User::factory()->administrator()->create();
        $this->nursingVoucher($warehouse, $user, 'María Hernández', now()->subHour(), '204-A');
        $this->manualVoucher($warehouse, $user, 'Paciente Diferente', now(), '318');

        $dates = ['date_from' => '2026-10-15', 'date_to' => '2026-10-15'];

        $this->actingAs($user)->get(route('reports.patient-vouchers.index', [
            ...$dates,
            'patient_name' => '  hernández  ',
        ]))->assertOk()
            ->assertSee('María Hernández')
            ->assertDontSee('Paciente Diferente')
            ->assertSee('value="hernández"', false);

        $this->get(route('reports.patient-vouchers.index', [
            ...$dates,
            'room_number' => '318',
        ]))->assertOk()
            ->assertSee('Paciente Diferente')
            ->assertDontSee('María Hernández')
            ->assertSee('value="318"', false);

        $this->get(route('reports.patient-vouchers.index', [
            ...$dates,
            'patient_name' => 'María',
            'room_number' => '318',
        ]))->assertOk()
            ->assertSee('No hay vales que coincidan con los filtros.');
    }

    private function nursingVoucher(Warehouse $warehouse, User $requester, string $patient, Carbon $at, string $roomNumber = '204'): NursingVoucher
    {
        return NursingVoucher::create([
            'requested_by' => $requester->id,
            'warehouse_id' => $warehouse->id,
            'source_type' => NursingSupplySourceType::WAREHOUSE,
            'external_patient_id' => 'patient-report',
            'external_hospitalization_id' => 'hospitalization-report-'.uniqid(),
            'patient_name' => $patient,
            'external_room_id' => 'room-report',
            'room_number' => $roomNumber,
            'status' => NursingVoucherStatus::SUPPLIED,
            'requested_at' => $at,
            'completed_at' => $at,
        ]);
    }

    private function manualVoucher(Warehouse $warehouse, User $user, string $patient, Carbon $at, string $roomNumber = '204'): InventoryOutbound
    {
        return $warehouse->inventoryOutbounds()->create([
            'reason' => InventoryOutboundReason::PATIENT_CARE,
            'patient_name' => $patient,
            'room_number' => $roomNumber,
            'processed_by' => $user->id,
            'processed_at' => $at,
            'source_type' => InventoryOutbound::SOURCE_MANUAL,
        ]);
    }
}
