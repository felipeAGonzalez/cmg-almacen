<?php

namespace Tests\Feature;

use App\Enums\AdministrationVoucherStatus;
use App\Models\AdministrationVoucher;
use App\Models\Cabinet;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\AdministrationVoucherNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdministrationVoucherNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_actions_are_scoped_deduplicated_closed_and_requester_is_informed(): void
    {
        [$voucher,$requester,$warehouse] = $this->voucher();
        $manager = User::factory()->warehouseManager()->create();
        $manager->warehouses()->attach($warehouse);
        $foreign = User::factory()->warehouseManager()->create();
        $foreign->warehouses()->attach(Warehouse::factory()->create());
        $service = app(AdministrationVoucherNotificationService::class);
        $service->notifyCreated($voucher);
        $service->notifyCreated($voucher);
        $this->assertSame(1, $manager->unreadNotifications()->count());
        $this->assertSame(0, $foreign->unreadNotifications()->count());
        $voucher->update(['status' => AdministrationVoucherStatus::PARTIALLY_SUPPLIED]);
        $service->notifyAfterFulfillment($voucher->fresh());
        $this->assertSame(1, $manager->unreadNotifications()->count());
        $manager->unreadNotifications()->first()->markAsRead();
        $service->notifyAfterFulfillment($voucher->fresh());
        $this->assertSame(1, $manager->unreadNotifications()->count());
        $voucher->update(['status' => AdministrationVoucherStatus::SUPPLIED]);
        $service->notifyAfterFulfillment($voucher->fresh());
        $this->assertSame(0, $manager->unreadNotifications()->count());
        $this->assertSame('administration_voucher_supplied', $requester->unreadNotifications()->first()->data['kind']);
    }

    public function test_rejection_notifies_requester_and_reading_does_not_change_status(): void
    {
        [$voucher,$requester] = $this->voucher();
        $voucher->update(['status' => AdministrationVoucherStatus::REJECTED]);
        app(AdministrationVoucherNotificationService::class)->notifyRejected($voucher->fresh());
        $notification = $requester->unreadNotifications()->first();
        $this->actingAs($requester)->post(route('notifications.read', $notification))->assertRedirect();
        $this->assertSame(AdministrationVoucherStatus::REJECTED, $voucher->fresh()->status);
        $this->assertNotNull($notification->fresh()->read_at);
    }

    private function voucher(): array
    {
        $requester = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create();
        $cabinet = Cabinet::factory()->for($warehouse)->create();
        $voucher = AdministrationVoucher::create(['requested_by' => $requester->id, 'warehouse_id' => $warehouse->id, 'cabinet_id' => $cabinet->id, 'status' => AdministrationVoucherStatus::PENDING, 'requested_at' => now()]);

        return [$voucher, $requester, $warehouse];
    }
}
