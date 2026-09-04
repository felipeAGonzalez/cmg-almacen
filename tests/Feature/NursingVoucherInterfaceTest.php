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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NursingVoucherInterfaceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_navigation_is_available_to_operational_roles(): void
    {
        foreach ([UserRole::NURSE, UserRole::WAREHOUSE_MANAGER, UserRole::ADMINISTRATOR, UserRole::ROOT] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('home'))->assertOk()->assertSee('Vales de Enfermería');
        }
    }

    public function test_hospital_context_links_to_creation_for_nurse(): void
    {
        $nurse = User::factory()->create(['role' => UserRole::NURSE]);
        $this->actingAs($nurse)->withSession(['hospital_context' => $this->hospitalContext()])
            ->get(route('nursing.hospital-context'))
            ->assertOk()->assertSee('Crear vale de Enfermería')
            ->assertSee(route('nursing-vouchers.create'));
    }

    public function test_create_revalidates_patient_and_shows_only_warehouse_source_products(): void
    {
        Carbon::setTestNow('2026-09-07 10:00:00');
        [$nurse, $warehouse, $cabinet] = $this->nurseContext();
        $allowed = Product::factory()->create(['name' => 'Producto permitido', 'code' => 'PER-1']);
        $foreign = Product::factory()->create(['name' => 'Producto ajeno']);
        InventoryItem::factory()->forWarehouse($warehouse)->for($allowed)->create();
        InventoryItem::factory()->forCabinet($cabinet)->for($foreign)->create();
        $this->fakeHospital();

        $this->actingAs($nurse)->withSession(['hospital_context' => $this->hospitalContext()])
            ->get(route('nursing-vouchers.create'))
            ->assertOk()
            ->assertSee('Paciente validado')->assertSee('Habitación 204')
            ->assertSee('El vale será enviado al almacén para su surtido.')
            ->assertSee('Producto permitido')->assertDontSee('Producto ajeno')
            ->assertDontSee('name="source_type"', false);
    }

    public function test_create_shows_cabinet_source_and_stale_context_blocks_form(): void
    {
        Carbon::setTestNow('2026-09-07 18:00:00');
        [$nurse, $warehouse, $cabinet] = $this->nurseContext();
        $product = Product::factory()->create(['name' => 'Producto de gabinete']);
        InventoryItem::factory()->forCabinet($cabinet)->for($product)->create();
        $this->fakeHospital();

        $this->actingAs($nurse)->withSession(['hospital_context' => $this->hospitalContext()])
            ->get(route('nursing-vouchers.create'))
            ->assertOk()->assertSee('El vale se surtirá desde el gabinete.')
            ->assertSee($cabinet->name)->assertSee('Producto de gabinete');

    }

    public function test_stale_hospital_context_blocks_creation_form(): void
    {
        Carbon::setTestNow('2026-09-07 18:00:00');
        [$nurse] = $this->nurseContext();
        Http::fake(['*' => Http::response(['data' => []])]);

        $this->actingAs($nurse)->withSession(['hospital_context' => $this->hospitalContext()])
            ->get(route('nursing-vouchers.create'))
            ->assertOk()->assertSee('La hospitalización ya no se encuentra activa.')
            ->assertDontSee('data-nursing-voucher-form', false);
    }

    public function test_only_nurse_can_open_create_and_old_multiple_items_are_rendered(): void
    {
        $manager = User::factory()->create(['role' => UserRole::WAREHOUSE_MANAGER]);
        $this->actingAs($manager)->get(route('nursing-vouchers.create'))->assertForbidden();

        Carbon::setTestNow('2026-09-07 10:00:00');
        [$nurse, $warehouse] = $this->nurseContext();
        $products = Product::factory()->count(2)->create();
        $products->each(fn (Product $product) => InventoryItem::factory()->forWarehouse($warehouse)->for($product)->create());
        $this->fakeHospital();

        $this->actingAs($nurse)->withSession([
            'hospital_context' => $this->hospitalContext(),
            '_old_input' => ['items' => [
                ['product_id' => $products[0]->id, 'quantity' => '1.250'],
                ['product_id' => $products[1]->id, 'quantity' => '2.500'],
            ]],
        ])->get(route('nursing-vouchers.create'))
            ->assertOk()->assertSee('1.250')->assertSee('2.500')
            ->assertSee('items[1][product_id]', false);
    }

    public function test_index_filters_status_dates_and_preserves_query_on_pagination(): void
    {
        $admin = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create();
        $visible = $this->voucher($admin, $warehouse, NursingVoucherStatus::PENDING, '2026-09-02 10:00:00');
        $rejected = $this->voucher($admin, $warehouse, NursingVoucherStatus::REJECTED, '2026-08-01 10:00:00');
        $rejected->update(['patient_name' => 'Paciente rechazado fuera de filtro']);
        for ($index = 0; $index < 26; $index++) {
            $this->voucher($admin, $warehouse, NursingVoucherStatus::PENDING, '2026-09-02 09:00:00');
        }

        $response = $this->actingAs($admin)->get(route('nursing-vouchers.index', [
            'status' => NursingVoucherStatus::PENDING->value,
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-03',
            'warehouse_id' => $warehouse->id,
        ]));

        $response->assertOk()->assertSee('Vales de Enfermería')->assertSee('#'.$visible->id)
            ->assertDontSee('Paciente rechazado fuera de filtro')->assertSee('page=2', false)->assertSee('status=pending', false);
    }

    public function test_show_displays_stock_partial_fulfillment_and_batch_traceability(): void
    {
        $warehouse = Warehouse::factory()->create();
        $nurse = User::factory()->create(['role' => UserRole::NURSE]);
        $nurse->warehouses()->attach($warehouse);
        $manager = User::factory()->create(['role' => UserRole::WAREHOUSE_MANAGER]);
        $manager->warehouses()->attach($warehouse);
        $product = Product::factory()->create(['name' => 'Paracetamol']);
        $inventoryItem = InventoryItem::factory()->forWarehouse($warehouse)->for($product)->create();
        $batch = $this->batch($inventoryItem, '6', 'LOT-FAB-1');
        $voucher = $this->voucher($nurse, $warehouse, NursingVoucherStatus::PARTIALLY_SUPPLIED);
        $voucherItem = $voucher->items()->create(['product_id' => $product->id, 'requested_quantity' => '10', 'supplied_quantity' => '4']);
        $fulfillment = $voucher->fulfillments()->create(['supplied_by' => $manager->id, 'supplied_at' => now(), 'notes' => 'Entrega parcial']);
        $fulfillmentItem = $fulfillment->items()->create(['nursing_voucher_item_id' => $voucherItem->id, 'quantity' => '4']);
        $fulfillmentItem->allocations()->create(['inventory_batch_id' => $batch->id, 'quantity' => '4']);

        $this->actingAs($manager)->get(route('nursing-vouchers.show', $voucher))
            ->assertOk()->assertSee('Parcialmente surtido')->assertSee('Disponible: 6')
            ->assertSee('Cantidad a surtir')->assertSee('Historial de surtidos')
            ->assertSee($batch->internal_lot)->assertSee('LOT-FAB-1')
            ->assertSee('Entrega parcial')->assertDontSee('unit_cost')->assertDontSee('Cancelar vale')->assertDontSee('Rechazar vale');
    }

    public function test_final_vouchers_are_read_only_and_access_is_scoped(): void
    {
        $warehouse = Warehouse::factory()->create();
        $requester = User::factory()->create(['role' => UserRole::NURSE]);
        $requester->warehouses()->attach($warehouse);
        $foreignNurse = User::factory()->create(['role' => UserRole::NURSE]);
        $foreignNurse->warehouses()->attach(Warehouse::factory()->create());
        $voucher = $this->voucher($requester, $warehouse, NursingVoucherStatus::SUPPLIED);

        $this->actingAs($requester)->get(route('nursing-vouchers.show', $voucher))
            ->assertOk()->assertDontSee('Surtir vale')->assertDontSee('Cancelar vale')->assertDontSee('Rechazar vale');
        $this->actingAs($foreignNurse)->get(route('nursing-vouchers.show', $voucher))->assertForbidden();
        $this->actingAs(User::factory()->administrator()->create())->get(route('nursing-vouchers.show', $voucher))->assertOk();
        $this->actingAs(User::factory()->create(['role' => UserRole::ROOT]))->get(route('nursing-vouchers.show', $voucher))->assertOk();
    }

    private function nurseContext(): array
    {
        $warehouse = Warehouse::factory()->create();
        $cabinet = Cabinet::factory()->for($warehouse)->create();
        $warehouse->update(['default_nursing_cabinet_id' => $cabinet->id]);
        $nurse = User::factory()->create(['role' => UserRole::NURSE]);
        $nurse->warehouses()->attach($warehouse);

        return [$nurse, $warehouse, $cabinet];
    }

    private function voucher(User $requester, Warehouse $warehouse, NursingVoucherStatus $status, ?string $requestedAt = null): NursingVoucher
    {
        return NursingVoucher::query()->create([
            'requested_by' => $requester->id,
            'warehouse_id' => $warehouse->id,
            'source_type' => NursingSupplySourceType::WAREHOUSE,
            'external_patient_id' => 'patient-1',
            'external_hospitalization_id' => 'stay-1',
            'patient_name' => 'Paciente validado',
            'external_room_id' => 'room-1',
            'room_number' => '204',
            'status' => $status,
            'requested_at' => $requestedAt ?? now(),
        ]);
    }

    private function batch(InventoryItem $inventoryItem, string $quantity, ?string $manufacturerLot = null): InventoryBatch
    {
        return InventoryBatch::query()->create([
            'inventory_item_id' => $inventoryItem->id,
            'internal_lot' => 'NV-UI-LOT',
            'manufacturer_lot' => $manufacturerLot,
            'expiration_date' => '2027-01-01',
            'received_quantity' => $quantity,
            'available_quantity' => $quantity,
            'unit_cost' => '99.0000',
        ]);
    }

    private function fakeHospital(): void
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
        return ['patient_id' => 'patient-1', 'hospitalization_id' => 'stay-1', 'room_id' => 'room-1', 'room_number' => '204'];
    }
}
