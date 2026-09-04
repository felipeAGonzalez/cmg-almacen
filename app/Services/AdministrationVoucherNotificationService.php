<?php

namespace App\Services;

use App\Enums\AdministrationVoucherStatus;
use App\Enums\UserRole;
use App\Models\AdministrationVoucher;
use App\Models\User;
use App\Notifications\AdministrationVoucherNotification;
use Illuminate\Notifications\DatabaseNotification;

class AdministrationVoucherNotificationService
{
    public const ACTION_REQUIRED = 'administration_action_required';

    public const SUPPLIED = 'administration_voucher_supplied';

    public const REJECTED = 'administration_voucher_rejected';

    public function notifyCreated(AdministrationVoucher $voucher): void
    {
        $this->notifyManagers($voucher, 'Hay un nuevo vale de Administración pendiente de reposición de gabinete.');
    }

    public function notifyAfterFulfillment(AdministrationVoucher $voucher): void
    {
        if ($voucher->status === AdministrationVoucherStatus::PARTIALLY_SUPPLIED) {
            $this->notifyManagers($voucher, 'Un vale de Administración fue surtido parcialmente y aún tiene productos pendientes.');
        } elseif ($voucher->status === AdministrationVoucherStatus::SUPPLIED) {
            $this->closeActions($voucher);
            $voucher->requester->notify(new AdministrationVoucherNotification($voucher, self::SUPPLIED, 'Tu vale de Administración fue surtido completamente.'));
        }
    }

    public function notifyRejected(AdministrationVoucher $voucher): void
    {
        $this->closeActions($voucher);
        $voucher->requester->notify(new AdministrationVoucherNotification($voucher, self::REJECTED, 'Tu vale de Administración fue rechazado.'));
    }

    public function closeActions(AdministrationVoucher $voucher): void
    {
        DatabaseNotification::query()->whereNull('read_at')->where('data->kind', self::ACTION_REQUIRED)
            ->where('data->administration_voucher_id', $voucher->getKey())->update(['read_at' => now(), 'updated_at' => now()]);
    }

    private function notifyManagers(AdministrationVoucher $voucher, string $message): void
    {
        $managers = User::query()->where('role', UserRole::WAREHOUSE_MANAGER)
            ->whereHas('warehouses', fn ($query) => $query->whereKey($voucher->warehouse_id))->get();
        foreach ($managers as $manager) {
            $exists = $manager->unreadNotifications()->where('data->kind', self::ACTION_REQUIRED)
                ->where('data->administration_voucher_id', $voucher->getKey())->exists();
            if (! $exists) {
                $manager->notify(new AdministrationVoucherNotification($voucher, self::ACTION_REQUIRED, $message));
            }
        }
    }
}
