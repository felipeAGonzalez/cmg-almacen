<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\OperationalSetting;
use App\Models\User;
use App\Services\OperationalScheduleService;
use Carbon\CarbonImmutable;
use Database\Seeders\OperationalSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_default_configuration_idempotently(): void
    {
        $this->seed(OperationalSettingSeeder::class);
        $this->seed(OperationalSettingSeeder::class);

        $this->assertDatabaseCount('operational_settings', 1);
        $setting = OperationalSetting::firstOrFail();
        $this->assertSame('09:00:00', $setting->warehouse_service_start_time);
        $this->assertSame('17:00:00', $setting->warehouse_service_end_time);
    }

    public function test_normal_schedule_uses_inclusive_start_and_exclusive_end(): void
    {
        $schedule = app(OperationalScheduleService::class);

        $this->assertFalse($schedule->isWarehouseServiceOpen($this->at('08:59')));
        $this->assertTrue($schedule->isWarehouseServiceOpen($this->at('09:00')));
        $this->assertTrue($schedule->isWarehouseServiceOpen($this->at('12:00')));
        $this->assertTrue($schedule->isWarehouseServiceOpen($this->at('16:59')));
        $this->assertFalse($schedule->isWarehouseServiceOpen($this->at('17:00')));
        $this->assertFalse($schedule->isWarehouseServiceOpen($this->at('18:00')));
    }

    public function test_schedule_crossing_midnight_is_evaluated_correctly(): void
    {
        OperationalSetting::current()->update([
            'warehouse_service_start_time' => '20:00',
            'warehouse_service_end_time' => '06:00',
        ]);
        $schedule = app(OperationalScheduleService::class);

        $this->assertTrue($schedule->crossesMidnight());
        $this->assertFalse($schedule->isWarehouseServiceOpen($this->at('19:59')));
        $this->assertTrue($schedule->isWarehouseServiceOpen($this->at('20:00')));
        $this->assertTrue($schedule->isWarehouseServiceOpen($this->at('23:00')));
        $this->assertTrue($schedule->isWarehouseServiceOpen($this->at('00:00')));
        $this->assertTrue($schedule->isWarehouseServiceOpen($this->at('05:59')));
        $this->assertFalse($schedule->isWarehouseServiceOpen($this->at('06:00')));
        $this->assertFalse($schedule->isWarehouseServiceOpen($this->at('12:00')));
    }

    public function test_validation_requires_distinct_valid_times_and_accepts_midnight_crossing(): void
    {
        $user = User::factory()->administrator()->create();
        $route = route('operational-settings.update');

        $this->actingAs($user)->put($route, [])->assertSessionHasErrors([
            'warehouse_service_start_time', 'warehouse_service_end_time',
        ]);
        $this->put($route, [
            'warehouse_service_start_time' => 'invalid',
            'warehouse_service_end_time' => '17:00',
        ])->assertSessionHasErrors('warehouse_service_start_time');
        $this->put($route, [
            'warehouse_service_start_time' => '09:00',
            'warehouse_service_end_time' => '09:00',
        ])->assertSessionHasErrors([
            'warehouse_service_start_time' => 'La hora de inicio y la hora de fin deben ser diferentes.',
        ]);
        $this->put($route, [
            'warehouse_service_start_time' => '20:00',
            'warehouse_service_end_time' => '06:00',
        ])->assertSessionHasNoErrors();
    }

    public function test_administrator_and_root_can_edit_and_navigation_is_visible(): void
    {
        foreach ([UserRole::ADMINISTRATOR, UserRole::ROOT] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('operational-settings.edit'))
                ->assertOk()
                ->assertSee('Horario operativo')
                ->assertSee('value="09:00"', false)
                ->assertSee('value="17:00"', false)
                ->assertSee(route('operational-settings.edit'), false);
        }
    }

    public function test_non_administrative_roles_cannot_access_or_see_schedule_navigation(): void
    {
        foreach ([UserRole::WAREHOUSE_MANAGER, UserRole::NURSE, UserRole::LEGACY_USER] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('operational-settings.edit'))->assertForbidden();
            $this->get(route('home'))->assertOk()->assertDontSee(route('operational-settings.edit'), false);
        }

        auth()->logout();
        $this->get(route('operational-settings.edit'))->assertRedirect(route('login'));
    }

    public function test_update_persists_single_row_and_service_uses_it_immediately(): void
    {
        $this->actingAs(User::factory()->administrator()->create())
            ->put(route('operational-settings.update'), [
                'warehouse_service_start_time' => '10:30',
                'warehouse_service_end_time' => '18:15',
            ])->assertRedirect(route('operational-settings.edit'))
            ->assertSessionHas('success', 'El horario operativo se actualizó correctamente.');

        $this->assertDatabaseCount('operational_settings', 1);
        $setting = OperationalSetting::firstOrFail();
        $this->assertSame('10:30:00', $setting->warehouse_service_start_time);
        $this->assertSame('18:15:00', $setting->warehouse_service_end_time);
        $this->assertFalse(app(OperationalScheduleService::class)->isWarehouseServiceOpen($this->at('10:29')));
        $this->assertTrue(app(OperationalScheduleService::class)->isWarehouseServiceOpen($this->at('10:30')));
    }

    public function test_service_respects_application_timezone_without_forcing_utc(): void
    {
        config(['app.timezone' => 'America/Mexico_City']);
        OperationalSetting::current()->update([
            'warehouse_service_start_time' => '09:00',
            'warehouse_service_end_time' => '17:00',
        ]);

        $localTime = CarbonImmutable::create(2026, 8, 31, 9, 0, 0, 'America/Mexico_City');
        $this->assertTrue(app(OperationalScheduleService::class)->isWarehouseServiceOpen($localTime));
        $this->assertTrue(app(OperationalScheduleService::class)->isWarehouseServiceOpen($localTime->utc()));
    }

    private function at(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse("2026-08-31 $time", config('app.timezone'));
    }
}
