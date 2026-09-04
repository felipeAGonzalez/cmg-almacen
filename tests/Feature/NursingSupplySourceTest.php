<?php

namespace Tests\Feature;

use App\Data\NursingSupplySource;
use App\Enums\NursingSupplySourceType;
use App\Enums\UserRole;
use App\Exceptions\NursingSupplyConfigurationException;
use App\Models\Cabinet;
use App\Models\OperationalSetting;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\NursingSupplySourceService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NursingSupplySourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_warehouse_can_exist_without_default_and_assign_own_cabinet(): void
    {
        $warehouse = Warehouse::factory()->create();
        $cabinet = Cabinet::factory()->for($warehouse)->create();

        $this->assertNull($warehouse->defaultNursingCabinet);

        $warehouse->update(['default_nursing_cabinet_id' => $cabinet->id]);

        $this->assertTrue($warehouse->fresh()->defaultNursingCabinet->is($cabinet));
        $this->assertTrue($warehouse->fresh()->defaultNursingCabinetOrFail()->is($cabinet));
    }

    public function test_admin_can_change_and_remove_default_cabinet(): void
    {
        $warehouse = Warehouse::factory()->create();
        [$first, $second] = Cabinet::factory()->count(2)->for($warehouse)->create();
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)->put(route('warehouses.update', $warehouse), [
            'name' => $warehouse->name,
            'default_nursing_cabinet_id' => $first->id,
        ])->assertSessionHasNoErrors();
        $this->assertTrue($warehouse->fresh()->defaultNursingCabinet->is($first));

        $this->put(route('warehouses.update', $warehouse), [
            'name' => $warehouse->name,
            'default_nursing_cabinet_id' => $second->id,
        ])->assertSessionHasNoErrors();
        $this->assertTrue($warehouse->fresh()->defaultNursingCabinet->is($second));

        $this->put(route('warehouses.update', $warehouse), [
            'name' => $warehouse->name,
            'default_nursing_cabinet_id' => '',
        ])->assertSessionHasNoErrors();
        $this->assertNull($warehouse->fresh()->default_nursing_cabinet_id);
    }

    public function test_default_cabinet_must_belong_to_warehouse(): void
    {
        $warehouse = Warehouse::factory()->create();
        $foreignCabinet = Cabinet::factory()->create();

        $this->actingAs(User::factory()->administrator()->create())
            ->put(route('warehouses.update', $warehouse), [
                'name' => $warehouse->name,
                'default_nursing_cabinet_id' => $foreignCabinet->id,
            ])->assertSessionHasErrors([
                'default_nursing_cabinet_id' => 'El gabinete predeterminado debe pertenecer al almacén seleccionado.',
            ]);

        $this->assertNull($warehouse->fresh()->default_nursing_cabinet_id);
    }

    public function test_deleting_default_cabinet_sets_warehouse_reference_to_null(): void
    {
        $cabinet = Cabinet::factory()->create();
        $warehouse = $cabinet->warehouse;
        $warehouse->update(['default_nursing_cabinet_id' => $cabinet->id]);

        $this->actingAs(User::factory()->administrator()->create())
            ->delete(route('warehouses.cabinets.destroy', [$warehouse, $cabinet]))
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($cabinet);
        $this->assertNull($warehouse->fresh()->default_nursing_cabinet_id);
    }

    public function test_edit_only_shows_cabinets_from_current_warehouse_and_selected_value(): void
    {
        $warehouse = Warehouse::factory()->create();
        $selected = Cabinet::factory()->for($warehouse)->create(['name' => 'Gabinete propio']);
        $foreign = Cabinet::factory()->create(['name' => 'Gabinete ajeno']);
        $warehouse->update(['default_nursing_cabinet_id' => $selected->id]);

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('warehouses.edit', $warehouse))
            ->assertOk()
            ->assertSee('Gabinete predeterminado de Enfermería')
            ->assertSee('Sin configurar')
            ->assertSee('Gabinete propio')
            ->assertDontSee('Gabinete ajeno')
            ->assertSee('value="'.$selected->id.'" selected', false);

        $this->assertNotSame($warehouse->id, $foreign->warehouse_id);
    }

    public function test_nurse_without_warehouse_fails(): void
    {
        $this->expectExceptionObject(
            new NursingSupplyConfigurationException('La enfermera no tiene un almacén asignado.'),
        );

        app(NursingSupplySourceService::class)->resolve(
            User::factory()->create(['role' => UserRole::NURSE]),
            $this->at('2026-09-07 10:00'),
        );
    }

    public function test_open_warehouse_is_resolved_without_default_cabinet(): void
    {
        $nurse = $this->nurseFor(Warehouse::factory()->create());
        $source = app(NursingSupplySourceService::class)->resolve($nurse, $this->at('2026-09-07 10:00'));

        $this->assertSource($source, NursingSupplySourceType::WAREHOUSE, $nurse->warehouseForNursing());
        $this->assertNull($source->cabinet);
    }

    public function test_closed_or_resting_warehouse_resolves_its_default_cabinet(): void
    {
        $warehouse = Warehouse::factory()->create();
        $cabinet = Cabinet::factory()->for($warehouse)->create();
        $warehouse->update(['default_nursing_cabinet_id' => $cabinet->id]);
        $nurse = $this->nurseFor($warehouse);
        $service = app(NursingSupplySourceService::class);

        $outside = $service->resolve($nurse, $this->at('2026-09-07 18:00'));
        $restDay = $service->resolve($nurse, $this->at('2026-09-06 10:00'));

        $this->assertSource($outside, NursingSupplySourceType::CABINET, $warehouse, $cabinet);
        $this->assertSource($restDay, NursingSupplySourceType::CABINET, $warehouse, $cabinet);
    }

    public function test_closed_or_resting_warehouse_without_default_fails(): void
    {
        $service = app(NursingSupplySourceService::class);
        $nurse = $this->nurseFor(Warehouse::factory()->create());

        foreach (['2026-09-07 18:00', '2026-09-06 10:00'] as $dateTime) {
            try {
                $service->resolve($nurse, $this->at($dateTime));
                $this->fail('A missing default nursing cabinet must fail.');
            } catch (NursingSupplyConfigurationException $exception) {
                $this->assertSame(
                    'El almacén no tiene configurado un gabinete predeterminado de Enfermería.',
                    $exception->getMessage(),
                );
            }
        }
    }

    public function test_different_warehouses_and_night_schedule_resolve_independently(): void
    {
        OperationalSetting::current()->update([
            'warehouse_service_start_time' => '20:00',
            'warehouse_service_end_time' => '06:00',
            'warehouse_rest_day' => 7,
        ]);
        $firstWarehouse = Warehouse::factory()->create();
        $secondWarehouse = Warehouse::factory()->create();
        $firstCabinet = Cabinet::factory()->for($firstWarehouse)->create();
        $secondCabinet = Cabinet::factory()->for($secondWarehouse)->create();
        $firstWarehouse->update(['default_nursing_cabinet_id' => $firstCabinet->id]);
        $secondWarehouse->update(['default_nursing_cabinet_id' => $secondCabinet->id]);
        $service = app(NursingSupplySourceService::class);

        $this->assertSource(
            $service->resolve($this->nurseFor($firstWarehouse), $this->at('2026-09-07 12:00')),
            NursingSupplySourceType::CABINET,
            $firstWarehouse,
            $firstCabinet,
        );
        $this->assertSource(
            $service->resolve($this->nurseFor($secondWarehouse), $this->at('2026-09-07 21:00')),
            NursingSupplySourceType::WAREHOUSE,
            $secondWarehouse,
        );
    }

    private function nurseFor(Warehouse $warehouse): User
    {
        $nurse = User::factory()->create(['role' => UserRole::NURSE]);
        $nurse->warehouses()->attach($warehouse);

        return $nurse;
    }

    private function at(string $dateTime): CarbonImmutable
    {
        return CarbonImmutable::parse($dateTime, config('app.timezone'));
    }

    private function assertSource(
        NursingSupplySource $source,
        NursingSupplySourceType $type,
        Warehouse $warehouse,
        ?Cabinet $cabinet = null,
    ): void {
        $this->assertSame($type, $source->type);
        $this->assertTrue($source->warehouse->is($warehouse));
        $this->assertSame($cabinet?->getKey(), $source->cabinet?->getKey());
    }
}
