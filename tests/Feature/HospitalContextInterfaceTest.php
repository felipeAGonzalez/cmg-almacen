<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Cabinet;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HospitalContextInterfaceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_context_prioritizes_patient_room_warehouse_and_warehouse_source(): void
    {
        Carbon::setTestNow('2026-09-21 10:00:00');
        [$nurse, $warehouse] = $this->nurseWithWarehouse();
        $this->fakeHospital();

        $response = $this->actingAs($nurse)
            ->withSession(['hospital_context' => $this->hospitalContext()])
            ->get(route('nursing.hospital-context'));

        $response->assertOk()
            ->assertSee('Paciente seleccionado desde Hospitalización')
            ->assertSee('Paciente validado')
            ->assertSee('Habitación 204')
            ->assertSee($warehouse->name)
            ->assertSee('Origen de surtido')
            ->assertSee('Horario operativo vigente')
            ->assertSee('Crear vale de Enfermería')
            ->assertSee(route('nursing-vouchers.create'))
            ->assertSee('Ver datos técnicos')
            ->assertDontSee('("create", \App\Models\NursingVoucher::class)', false)
            ->assertDontSee('Contexto recibido');
    }

    public function test_context_displays_default_cabinet_when_warehouse_is_closed(): void
    {
        Carbon::setTestNow('2026-09-21 20:00:00');
        [$nurse, $warehouse] = $this->nurseWithWarehouse();
        $cabinet = Cabinet::factory()->for($warehouse)->create(['name' => 'Gabinete Nocturno']);
        $warehouse->update(['default_nursing_cabinet_id' => $cabinet->id]);
        $this->fakeHospital();

        $this->actingAs($nurse)
            ->withSession(['hospital_context' => $this->hospitalContext()])
            ->get(route('nursing.hospital-context'))
            ->assertOk()
            ->assertSee('Gabinete de Enfermería')
            ->assertSee('Gabinete Nocturno')
            ->assertSee('fuera de horario operativo o en día de descanso');
    }

    public function test_missing_warehouse_and_default_cabinet_are_controlled(): void
    {
        Carbon::setTestNow('2026-09-21 20:00:00');
        $this->fakeHospital();
        $nurseWithoutWarehouse = User::factory()->create(['role' => UserRole::NURSE]);

        $this->actingAs($nurseWithoutWarehouse)
            ->withSession(['hospital_context' => $this->hospitalContext()])
            ->get(route('nursing.hospital-context'))
            ->assertOk()
            ->assertSee('La enfermera no tiene un almacén asignado.')
            ->assertDontSee('Crear vale de Enfermería');

        [$nurse] = $this->nurseWithWarehouse();
        $this->actingAs($nurse)
            ->withSession(['hospital_context' => $this->hospitalContext()])
            ->get(route('nursing.hospital-context'))
            ->assertOk()
            ->assertSee('No hay un gabinete de Enfermería configurado para este almacén.')
            ->assertDontSee('Crear vale de Enfermería');
    }

    public function test_hospital_failure_is_safe_and_does_not_show_technical_context(): void
    {
        [$nurse] = $this->nurseWithWarehouse();
        Http::fake(fn () => Http::response([], 500));

        $this->actingAs($nurse)
            ->withSession(['hospital_context' => $this->hospitalContext()])
            ->get(route('nursing.hospital-context'))
            ->assertOk()
            ->assertSee('No fue posible validar la información del paciente en Hospitalización.')
            ->assertDontSee('patient-1')
            ->assertDontSee('stay-1')
            ->assertDontSee('Crear vale de Enfermería');
    }

    public function test_context_route_rejects_unauthorized_users(): void
    {
        $this->get(route('nursing.hospital-context'))->assertRedirect(route('login'));

        foreach ([UserRole::ADMINISTRATOR, UserRole::ROOT, UserRole::WAREHOUSE_MANAGER, UserRole::LEGACY_USER] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('nursing.hospital-context'))
                ->assertForbidden();
        }
    }

    /** @return array{User, Warehouse} */
    private function nurseWithWarehouse(): array
    {
        $warehouse = Warehouse::factory()->create(['name' => 'Almacén Central']);
        $nurse = User::factory()->create(['role' => UserRole::NURSE]);
        $nurse->warehouses()->attach($warehouse);

        return [$nurse, $warehouse];
    }

    private function fakeHospital(): void
    {
        Http::fake(fn () => Http::response(['data' => [[
            'patient_id' => 'patient-1',
            'hospitalization_id' => 'stay-1',
            'patient_name' => 'Paciente validado',
            'room_id' => 'room-1',
            'room_number' => '204',
        ]]]));
    }

    /** @return array<string, string> */
    private function hospitalContext(): array
    {
        return [
            'patient_id' => 'patient-1',
            'hospitalization_id' => 'stay-1',
            'room_id' => 'room-1',
            'room_number' => '204',
        ];
    }
}
