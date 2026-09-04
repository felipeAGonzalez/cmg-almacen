<?php

namespace Tests\Feature;

use App\Enums\AdministrationVoucherStatus;
use App\Enums\NursingSupplySourceType;
use App\Enums\NursingVoucherStatus;
use App\Enums\UserRole;
use App\Models\AdministrationVoucher;
use App\Models\Cabinet;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\NursingVoucher;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('home'))->assertRedirect(route('login'));
    }

    public function test_administrator_and_root_see_global_summary_and_pending_configuration(): void
    {
        Warehouse::factory()->create();
        User::factory()->nurse()->create(['hospital_user_id' => null]);

        foreach ([UserRole::ADMINISTRATOR, UserRole::ROOT] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('home'))
                ->assertOk()->assertSee('Resumen global')->assertSee('Configuración pendiente')
                ->assertSee('Almacenes sin gabinete predeterminado')->assertSee('Enfermeras sin almacén asignado')
                ->assertSee('Enfermeras sin vínculo con Hospitalización');
        }
    }

    public function test_inventory_indicators_use_usable_stock_and_exclude_empty_expired_batches(): void
    {
        Carbon::setTestNow('2026-09-03 12:00:00');
        $warehouse = Warehouse::factory()->create();
        $critical = InventoryItem::factory()->forWarehouse($warehouse)->create(['minimum_stock' => 10, 'maximum_stock' => 20]);
        $low = InventoryItem::factory()->forWarehouse($warehouse)->create(['minimum_stock' => 10, 'maximum_stock' => 20]);
        $this->batch($critical, '8', '2026-09-04');
        $this->batch($critical, '4', '2026-09-02');
        $this->batch($low, '15', null);
        $this->batch($low, '0', '2026-09-01');

        $response = $this->actingAs(User::factory()->administrator()->create())->get(route('home'));
        $response->assertOk()->assertSeeInOrder(['1', 'Stock crítico'])->assertSeeInOrder(['1', 'Stock bajo'])
            ->assertSeeInOrder(['1', 'Lotes vencidos con existencia']);
    }

    public function test_manager_only_sees_assigned_warehouse_counts_and_attention_vouchers(): void
    {
        $manager = User::factory()->warehouseManager()->create();
        $requester = User::factory()->administrator()->create();
        $assigned = Warehouse::factory()->create(['name' => 'Almacén asignado']);
        $foreign = Warehouse::factory()->create(['name' => 'Almacén ajeno']);
        $manager->warehouses()->attach($assigned);
        $assignedCabinet = Cabinet::factory()->for($assigned)->create();
        $foreignCabinet = Cabinet::factory()->for($foreign)->create();
        $visible = $this->administrationVoucher($requester, $assigned, $assignedCabinet, AdministrationVoucherStatus::PENDING);
        $hidden = $this->administrationVoucher($requester, $foreign, $foreignCabinet, AdministrationVoucherStatus::PENDING);

        $this->actingAs($manager)->get(route('home'))->assertOk()->assertSee('Almacén asignado')
            ->assertSee('Vales por atender')->assertSee('#'.$visible->id)->assertDontSee('#'.$hidden->id)
            ->assertDontSee('Resumen global');
    }

    public function test_nurse_sees_own_operational_context_and_only_own_recent_vouchers(): void
    {
        Carbon::setTestNow('2026-09-01 10:00:00');
        $nurse = User::factory()->nurse()->create();
        $other = User::factory()->nurse()->create();
        $warehouse = Warehouse::factory()->create(['name' => 'Clínica Norte']);
        $cabinet = Cabinet::factory()->for($warehouse)->create(['name' => 'Gabinete nocturno']);
        $warehouse->update(['default_nursing_cabinet_id' => $cabinet->id]);
        $nurse->warehouses()->attach($warehouse);
        $other->warehouses()->attach($warehouse);
        $own = $this->nursingVoucher($nurse, $warehouse, NursingVoucherStatus::PENDING);
        $foreign = $this->nursingVoucher($other, $warehouse, NursingVoucherStatus::PENDING);

        $this->actingAs($nurse)->get(route('home'))->assertOk()->assertSee('Operación de Enfermería')
            ->assertSee('Clínica Norte')->assertSee('Gabinete nocturno')->assertSee('Almacén disponible')
            ->assertSee('Mis vales recientes')->assertSee('#'.$own->id)->assertDontSee('#'.$foreign->id)
            ->assertDontSee('Vales de Administración')->assertDontSee('Configuración pendiente');
    }

    public function test_nurse_sees_closed_state_without_duplicating_schedule_rules(): void
    {
        Carbon::setTestNow('2026-09-01 20:00:00');
        $nurse = User::factory()->nurse()->create();
        $warehouse = Warehouse::factory()->create();
        $nurse->warehouses()->attach($warehouse);

        $this->actingAs($nurse)->get(route('home'))->assertOk()->assertSee('Almacén cerrado')
            ->assertSee('Enfermería se surte desde gabinete.');
    }

    public function test_unread_notification_count_is_visible(): void
    {
        $admin = User::factory()->administrator()->create();
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(), 'type' => 'Dashboard test', 'notifiable_type' => 'user',
            'notifiable_id' => $admin->id, 'data' => '{}', 'read_at' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($admin)->get(route('home'))->assertOk()->assertSee('Notificaciones')->assertSee('1');
    }

    public function test_dashboard_query_count_does_not_grow_per_inventory_row(): void
    {
        $admin = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create();
        InventoryItem::factory()->count(2)->forWarehouse($warehouse)->create();
        $base = $this->dashboardQueryCount($admin);
        InventoryItem::factory()->count(20)->forWarehouse($warehouse)->create();
        $expanded = $this->dashboardQueryCount($admin);

        $this->assertLessThanOrEqual($base + 1, $expanded);
    }

    private function dashboardQueryCount(User $user): int
    {
        $count = 0;
        DB::listen(function () use (&$count): void {
            $count++;
        });
        $this->actingAs($user)->get(route('home'))->assertOk();

        return $count;
    }

    private function batch(InventoryItem $item, string $quantity, ?string $expiration): void
    {
        InventoryBatch::create([
            'inventory_item_id' => $item->id, 'entry_item_id' => null, 'source_batch_id' => null,
            'internal_lot' => 'DASH-'.Str::uuid(), 'expiration_date' => $expiration,
            'received_quantity' => $quantity, 'available_quantity' => $quantity, 'unit_cost' => '1.0000',
        ]);
    }

    private function nursingVoucher(User $requester, Warehouse $warehouse, NursingVoucherStatus $status): NursingVoucher
    {
        return NursingVoucher::create([
            'requested_by' => $requester->id, 'warehouse_id' => $warehouse->id,
            'source_type' => NursingSupplySourceType::WAREHOUSE, 'external_patient_id' => 'p',
            'external_hospitalization_id' => (string) Str::uuid(), 'patient_name' => 'Paciente de prueba',
            'external_room_id' => 'r', 'room_number' => '101', 'status' => $status, 'requested_at' => now(),
        ]);
    }

    private function administrationVoucher(User $requester, Warehouse $warehouse, Cabinet $cabinet, AdministrationVoucherStatus $status): AdministrationVoucher
    {
        return AdministrationVoucher::create([
            'requested_by' => $requester->id, 'warehouse_id' => $warehouse->id, 'cabinet_id' => $cabinet->id,
            'status' => $status, 'requested_at' => now(),
        ]);
    }
}
