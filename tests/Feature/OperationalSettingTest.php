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
        $this->assertSame(7, $setting->warehouse_rest_day);
    }

    public function test_normal_schedule_uses_inclusive_start_and_exclusive_end(): void
    {
        $schedule = app(OperationalScheduleService::class);

        $this->assertFalse($schedule->isWarehouseAvailableAt($this->at('08:59')));
        $this->assertTrue($schedule->isWarehouseAvailableAt($this->at('09:00')));
        $this->assertTrue($schedule->isWarehouseServiceOpen($this->at('12:00')));
        $this->assertTrue($schedule->isWarehouseServiceOpen($this->at('16:59')));
        $this->assertFalse($schedule->isWarehouseServiceOpen($this->at('17:00')));
        $this->assertFalse($schedule->isWarehouseServiceOpen($this->at('18:00')));
    }

    public function test_rest_day_is_closed_all_day_and_other_days_work_normally(): void
    {
        OperationalSetting::current()->update(['warehouse_rest_day' => 7]);
        $schedule = app(OperationalScheduleService::class);

        $this->assertFalse($schedule->isWarehouseServiceOpen($this->on('2026-09-06', '10:00')));
        $this->assertFalse($schedule->isWarehouseServiceOpen($this->on('2026-09-06', '20:00')));
        $this->assertTrue($schedule->isWarehouseServiceOpen($this->on('2026-09-07', '10:00')));
    }

    public function test_schedule_crossing_midnight_respects_calendar_rest_day(): void
    {
        OperationalSetting::current()->update([
            'warehouse_service_start_time' => '20:00',
            'warehouse_service_end_time' => '06:00',
            'warehouse_rest_day' => 7,
        ]);
        $schedule = app(OperationalScheduleService::class);

        $this->assertTrue($schedule->crossesMidnight());
        $this->assertTrue($schedule->isWarehouseServiceOpen($this->on('2026-09-05', '23:00')));
        $this->assertFalse($schedule->isWarehouseServiceOpen($this->on('2026-09-06', '02:00')));
        $this->assertFalse($schedule->isWarehouseServiceOpen($this->on('2026-09-06', '22:00')));
        $this->assertTrue($schedule->isWarehouseServiceOpen($this->on('2026-09-07', '02:00')));
        $this->assertFalse($schedule->isWarehouseServiceOpen($this->on('2026-09-07', '06:00')));
    }

    public function test_validation_requires_valid_rest_day_and_accepts_midnight_crossing(): void
    {
        $user = User::factory()->administrator()->create();
        $route = route('operational-settings.update');

        $this->actingAs($user)->put($route, [])->assertSessionHasErrors([
            'warehouse_service_start_time', 'warehouse_service_end_time', 'warehouse_rest_day',
        ]);

        foreach ([0, 8] as $invalidDay) {
            $this->put($route, $this->schedulePayload(['warehouse_rest_day' => $invalidDay]))
                ->assertSessionHasErrors('warehouse_rest_day');
        }

        $this->put($route, $this->schedulePayload([
            'warehouse_service_start_time' => '09:00',
            'warehouse_service_end_time' => '09:00',
        ]))->assertSessionHasErrors([
            'warehouse_service_start_time' => 'La hora de inicio y la hora de fin deben ser diferentes.',
        ]);

        $this->put($route, $this->schedulePayload([
            'warehouse_service_start_time' => '20:00',
            'warehouse_service_end_time' => '06:00',
        ]))->assertSessionHasNoErrors();
    }

    public function test_administrator_and_root_can_update_and_others_cannot(): void
    {
        foreach ([UserRole::ADMINISTRATOR, UserRole::ROOT] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->put(route('operational-settings.update'), $this->schedulePayload(['warehouse_rest_day' => 6]))
                ->assertRedirect(route('operational-settings.edit'));
        }

        foreach ([UserRole::WAREHOUSE_MANAGER, UserRole::NURSE, UserRole::LEGACY_USER] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->put(route('operational-settings.update'), $this->schedulePayload())
                ->assertForbidden();
        }
    }

    public function test_form_shows_spanish_rest_days_and_update_persists_single_row(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)->get(route('operational-settings.edit'))
            ->assertOk()
            ->assertSee('Día de descanso del almacenista')
            ->assertSee('Domingo')
            ->assertSee('Durante el día de descanso, Enfermería se surtirá desde el gabinete.');

        $this->put(route('operational-settings.update'), $this->schedulePayload([
            'warehouse_service_start_time' => '10:30',
            'warehouse_service_end_time' => '18:15',
            'warehouse_rest_day' => 3,
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('operational_settings', 1);
        $setting = OperationalSetting::firstOrFail();
        $this->assertSame('10:30:00', $setting->warehouse_service_start_time);
        $this->assertSame('18:15:00', $setting->warehouse_service_end_time);
        $this->assertSame(3, $setting->warehouse_rest_day);
    }

    public function test_service_respects_application_timezone(): void
    {
        config(['app.timezone' => 'America/Mexico_City']);
        OperationalSetting::current()->update(['warehouse_rest_day' => 7]);

        $localTime = CarbonImmutable::create(2026, 8, 31, 9, 0, 0, 'America/Mexico_City');
        $this->assertTrue(app(OperationalScheduleService::class)->isWarehouseServiceOpen($localTime));
        $this->assertTrue(app(OperationalScheduleService::class)->isWarehouseServiceOpen($localTime->utc()));
    }

    private function schedulePayload(array $overrides = []): array
    {
        return array_merge([
            'warehouse_service_start_time' => '09:00',
            'warehouse_service_end_time' => '17:00',
            'warehouse_rest_day' => 7,
        ], $overrides);
    }

    private function at(string $time): CarbonImmutable
    {
        return $this->on('2026-08-31', $time);
    }

    private function on(string $date, string $time): CarbonImmutable
    {
        return CarbonImmutable::parse("$date $time", config('app.timezone'));
    }
}
