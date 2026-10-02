<?php

namespace Tests\Feature;

use App\Enums\AdministrationVoucherStatus;
use App\Enums\NursingSupplySourceType;
use App\Enums\NursingVoucherStatus;
use App\Models\AdministrationVoucher;
use App\Models\Cabinet;
use App\Models\NursingVoucher;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\AdministrationVoucherNotification;
use App\Notifications\NursingVoucherActionRequired;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Tests\TestCase;

class RealtimeNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_voucher_notifications_use_database_and_broadcast_channels(): void
    {
        $nursing = new NursingVoucherActionRequired($this->nursingVoucher(), 'action_required', 'Nuevo vale');
        $administration = new AdministrationVoucherNotification($this->administrationVoucher(), 'action_required', 'Nueva reposición');

        $this->assertSame(['database', 'broadcast'], $nursing->via(new \stdClass));
        $this->assertSame(['database', 'broadcast'], $administration->via(new \stdClass));
        $this->assertSame('/nursing/vouchers/1', $nursing->toBroadcast(new \stdClass)->data['action_url']);
        $this->assertSame('/administration/vouchers/1', $administration->toBroadcast(new \stdClass)->data['action_url']);
    }

    public function test_private_user_channel_authorizes_its_owner(): void
    {
        $this->useReverbBroadcaster();
        $owner = User::factory()->create();
        $payload = ['channel_name' => "private-App.Models.User.{$owner->id}", 'socket_id' => '123.456'];

        $this->actingAs($owner)->post('/broadcasting/auth', $payload)->assertOk();
    }

    public function test_private_user_channel_rejects_another_user(): void
    {
        $this->useReverbBroadcaster();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $payload = ['channel_name' => "private-App.Models.User.{$owner->id}", 'socket_id' => '123.456'];

        $this->actingAs($other)->post('/broadcasting/auth', $payload)->assertForbidden();
    }

    public function test_authenticated_layout_exposes_runtime_reverb_configuration_and_live_badge(): void
    {
        config([
            'broadcasting.connections.reverb.key' => 'public-key',
            'broadcasting.connections.reverb.options.host' => 'realtime.example.test',
            'broadcasting.connections.reverb.options.port' => 443,
            'broadcasting.connections.reverb.options.scheme' => 'https',
        ]);
        $user = User::factory()->administrator()->create();

        $this->actingAs($user)->get(route('home'))
            ->assertOk()
            ->assertSee('data-realtime-user-id="'.$user->id.'"', false)
            ->assertSee('data-reverb-host="realtime.example.test"', false)
            ->assertSee('data-notification-badge', false)
            ->assertSee('data-realtime-notifications', false);
    }

    private function nursingVoucher(): NursingVoucher
    {
        return NursingVoucher::create([
            'requested_by' => User::factory()->nurse()->create()->id,
            'warehouse_id' => Warehouse::factory()->create()->id,
            'source_type' => NursingSupplySourceType::WAREHOUSE,
            'external_patient_id' => 1,
            'external_hospitalization_id' => 1,
            'patient_name' => 'Paciente prueba',
            'external_room_id' => 1,
            'room_number' => '101',
            'status' => NursingVoucherStatus::PENDING,
            'requested_at' => now(),
        ]);
    }

    private function useReverbBroadcaster(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);
        Broadcast::getFacadeRoot()->forgetDrivers();
        Broadcast::channel('App.Models.User.{id}', fn (User $user, int $id): bool => $user->getKey() === $id);
    }

    private function administrationVoucher(): AdministrationVoucher
    {
        $warehouse = Warehouse::factory()->create();

        return AdministrationVoucher::create([
            'requested_by' => User::factory()->administrator()->create()->id,
            'warehouse_id' => $warehouse->id,
            'cabinet_id' => Cabinet::factory()->for($warehouse)->create()->id,
            'status' => AdministrationVoucherStatus::PENDING,
            'requested_at' => now(),
        ]);
    }
}
