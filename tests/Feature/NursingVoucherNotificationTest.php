<?php

namespace Tests\Feature;

use App\Enums\NursingSupplySourceType;
use App\Enums\NursingVoucherStatus;
use App\Enums\UserRole;
use App\Models\Cabinet;
use App\Models\NursingVoucher;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\NursingVoucherActionRequired;
use App\Services\NursingVoucherNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NursingVoucherNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_warehouse_voucher_notifies_only_assigned_managers_and_administrators_without_requester_noise(): void
    {
        [$voucher, $requester, $warehouse] = $this->voucher(NursingSupplySourceType::WAREHOUSE);
        $assigned = User::factory()->warehouseManager()->create();
        $secondAssigned = User::factory()->warehouseManager()->create();
        $foreign = User::factory()->warehouseManager()->create();
        $admin = User::factory()->administrator()->create();
        $root = User::factory()->create(['role' => UserRole::ROOT]);
        $assigned->warehouses()->attach($warehouse);
        $secondAssigned->warehouses()->attach($warehouse);
        $foreign->warehouses()->attach(Warehouse::factory()->create());

        $service = app(NursingVoucherNotificationService::class);
        $service->notifyCreated($voucher);
        $service->notifyCreated($voucher);

        $this->assertSame(1, $assigned->unreadNotifications()->count());
        $this->assertSame(1, $secondAssigned->unreadNotifications()->count());
        $this->assertSame(0, $foreign->unreadNotifications()->count());
        $this->assertSame(1, $admin->unreadNotifications()->count());
        $this->assertSame(1, $root->unreadNotifications()->count());
        $this->assertSame(0, $requester->unreadNotifications()->count());
        $this->assertSame('action_required', $assigned->unreadNotifications()->first()->data['kind']);
    }

    public function test_cabinet_voucher_notifies_same_warehouse_nurses_except_requester_and_global_administrators(): void
    {
        [$voucher, $requester, $warehouse] = $this->voucher(NursingSupplySourceType::CABINET);
        $sameWarehouse = User::factory()->nurse()->create();
        $foreign = User::factory()->nurse()->create();
        $admin = User::factory()->administrator()->create();
        $root = User::factory()->create(['role' => UserRole::ROOT]);
        $sameWarehouse->warehouses()->attach($warehouse);
        $foreign->warehouses()->attach(Warehouse::factory()->create());

        app(NursingVoucherNotificationService::class)->notifyCreated($voucher);

        $this->assertSame(1, $sameWarehouse->unreadNotifications()->count());
        $this->assertSame(0, $foreign->unreadNotifications()->count());
        $this->assertSame(0, $requester->unreadNotifications()->count());
        $this->assertSame(1, $admin->unreadNotifications()->count());
        $this->assertSame(1, $root->unreadNotifications()->count());
        $this->assertStringContainsString('gabinete', $sameWarehouse->unreadNotifications()->first()->data['message']);
    }

    public function test_partial_fulfillment_deduplicates_unread_actions_but_creates_a_new_one_after_reading(): void
    {
        [$voucher, , $warehouse] = $this->voucher(NursingSupplySourceType::WAREHOUSE);
        $manager = User::factory()->warehouseManager()->create();
        $manager->warehouses()->attach($warehouse);
        $service = app(NursingVoucherNotificationService::class);

        $service->notifyCreated($voucher);
        $voucher->update(['status' => NursingVoucherStatus::PARTIALLY_SUPPLIED]);
        $service->notifyAfterFulfillment($voucher->fresh());
        $this->assertSame(1, $manager->unreadNotifications()->count());

        $manager->unreadNotifications()->first()->markAsRead();
        $service->notifyAfterFulfillment($voucher->fresh());
        $this->assertSame(1, $manager->unreadNotifications()->count());
        $this->assertSame(2, $manager->notifications()->count());

        $this->actingAs($manager)->post(route('notifications.read', $manager->unreadNotifications()->first()))->assertRedirect();
        $this->assertSame(NursingVoucherStatus::PARTIALLY_SUPPLIED, $voucher->fresh()->status);
    }

    public function test_supplied_and_rejected_vouchers_close_actions_and_inform_requester(): void
    {
        [$supplied, $requester, $warehouse] = $this->voucher(NursingSupplySourceType::WAREHOUSE);
        $manager = User::factory()->warehouseManager()->create();
        $manager->warehouses()->attach($warehouse);
        $service = app(NursingVoucherNotificationService::class);
        $service->notifyCreated($supplied);

        $supplied->update(['status' => NursingVoucherStatus::SUPPLIED]);
        $service->notifyAfterFulfillment($supplied->fresh());
        $this->assertSame(0, $manager->unreadNotifications()->count());
        $this->assertSame('voucher_supplied', $requester->unreadNotifications()->first()->data['kind']);

        [$rejected, $rejectedRequester] = $this->voucher(NursingSupplySourceType::WAREHOUSE);
        $service->notifyCreated($rejected);
        $rejected->update(['status' => NursingVoucherStatus::REJECTED]);
        $service->notifyRejected($rejected->fresh());
        $this->assertSame('voucher_rejected', $rejectedRequester->unreadNotifications()->first()->data['kind']);
        $this->assertSame(0, $manager->unreadNotifications()->where('data->nursing_voucher_id', $rejected->id)->count());
    }

    public function test_cancellation_only_informs_requester_when_performed_by_someone_else(): void
    {
        [$ownVoucher, $requester] = $this->voucher(NursingSupplySourceType::WAREHOUSE);
        $service = app(NursingVoucherNotificationService::class);
        $ownVoucher->update(['status' => NursingVoucherStatus::CANCELLED]);
        $service->notifyCancelled($ownVoucher->fresh(), $requester);
        $this->assertSame(0, $requester->notifications()->count());

        [$adminVoucher, $otherRequester] = $this->voucher(NursingSupplySourceType::WAREHOUSE);
        $admin = User::factory()->administrator()->create();
        $adminVoucher->update(['status' => NursingVoucherStatus::CANCELLED]);
        $service->notifyCancelled($adminVoucher->fresh(), $admin);
        $this->assertSame('voucher_cancelled', $otherRequester->unreadNotifications()->first()->data['kind']);
    }

    public function test_notification_inbox_is_scoped_paginated_and_read_all_only_affects_owner(): void
    {
        [$voucher, $user] = $this->voucher(NursingSupplySourceType::WAREHOUSE);
        $other = User::factory()->nurse()->create();

        foreach (range(1, 26) as $number) {
            $user->notify(new NursingVoucherActionRequired($voucher, 'action_required', "Aviso {$number}"));
        }
        $other->notify(new NursingVoucherActionRequired($voucher, 'action_required', 'Aviso ajeno'));

        $response = $this->actingAs($user)->get(route('notifications.index'));
        $response->assertOk()
            ->assertSee('Notificaciones')
            ->assertSee('Pendientes')
            ->assertSee('Ver vale')
            ->assertSee('26')
            ->assertDontSee($voucher->external_hospitalization_id);

        $foreignNotification = $other->unreadNotifications()->first();
        $this->post(route('notifications.read', $foreignNotification))->assertForbidden();
        $this->post(route('notifications.read-all'))->assertRedirect();

        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->assertSame(1, $other->unreadNotifications()->count());
        $this->assertSame(NursingVoucherStatus::PENDING, $voucher->fresh()->status);
    }

    private function voucher(NursingSupplySourceType $source): array
    {
        $warehouse = Warehouse::factory()->create();
        $requester = User::factory()->nurse()->create();
        $requester->warehouses()->attach($warehouse);
        $cabinet = $source === NursingSupplySourceType::CABINET
            ? Cabinet::factory()->create(['warehouse_id' => $warehouse->id])
            : null;

        $voucher = NursingVoucher::query()->create([
            'requested_by' => $requester->id,
            'warehouse_id' => $warehouse->id,
            'source_type' => $source,
            'source_cabinet_id' => $cabinet?->id,
            'external_patient_id' => 'patient-private',
            'external_hospitalization_id' => 'stay-private',
            'patient_name' => 'Paciente de prueba',
            'external_room_id' => 'room-private',
            'room_number' => '204',
            'status' => NursingVoucherStatus::PENDING,
            'requested_at' => now(),
        ]);

        return [$voucher, $requester, $warehouse, $cabinet];
    }
}
