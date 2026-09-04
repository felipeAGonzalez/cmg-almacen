<?php

namespace App\Services;

use App\Enums\NursingSupplySourceType;
use App\Enums\NursingVoucherStatus;
use App\Enums\UserRole;
use App\Models\NursingVoucher;
use App\Models\User;
use App\Notifications\NursingVoucherActionRequired;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

class NursingVoucherNotificationService
{
    public const ACTION_REQUIRED = 'action_required';

    public const VOUCHER_SUPPLIED = 'voucher_supplied';

    public const VOUCHER_REJECTED = 'voucher_rejected';

    public const VOUCHER_CANCELLED = 'voucher_cancelled';

    public function notifyCreated(NursingVoucher $voucher): void
    {
        $message = $voucher->source_type === NursingSupplySourceType::WAREHOUSE
            ? 'Hay un nuevo vale de Enfermería pendiente de surtir.'
            : 'Hay un nuevo vale de Enfermería pendiente de surtir desde el gabinete.';

        $this->notifyActionRecipients($voucher, $message);
    }

    public function notifyAfterFulfillment(NursingVoucher $voucher): void
    {
        if ($voucher->status === NursingVoucherStatus::PARTIALLY_SUPPLIED) {
            $this->notifyActionRecipients(
                $voucher,
                'Un vale de Enfermería fue surtido parcialmente y aún tiene productos pendientes.',
            );

            return;
        }

        if ($voucher->status === NursingVoucherStatus::SUPPLIED) {
            $this->closePendingActions($voucher);
            $this->notifyRequester(
                $voucher,
                self::VOUCHER_SUPPLIED,
                'Tu vale de Enfermería fue surtido completamente.',
            );
        }
    }

    public function notifyRejected(NursingVoucher $voucher): void
    {
        $this->closePendingActions($voucher);
        $this->notifyRequester($voucher, self::VOUCHER_REJECTED, 'Tu vale de Enfermería fue rechazado.');
    }

    public function notifyCancelled(NursingVoucher $voucher, User $cancelledBy): void
    {
        $this->closePendingActions($voucher);

        if (! $voucher->requester->is($cancelledBy)) {
            $this->notifyRequester($voucher, self::VOUCHER_CANCELLED, 'Tu vale de Enfermería fue cancelado.');
        }
    }

    public function closePendingActions(NursingVoucher $voucher): void
    {
        DatabaseNotification::query()
            ->whereNull('read_at')
            ->where('data->kind', self::ACTION_REQUIRED)
            ->where('data->nursing_voucher_id', $voucher->getKey())
            ->update(['read_at' => now(), 'updated_at' => now()]);
    }

    private function notifyActionRecipients(NursingVoucher $voucher, string $message): void
    {
        foreach ($this->actionRecipients($voucher) as $recipient) {
            $exists = $recipient->unreadNotifications()
                ->where('data->kind', self::ACTION_REQUIRED)
                ->where('data->nursing_voucher_id', $voucher->getKey())
                ->exists();

            if (! $exists) {
                $recipient->notify(new NursingVoucherActionRequired($voucher, self::ACTION_REQUIRED, $message));
            }
        }
    }

    private function actionRecipients(NursingVoucher $voucher): Collection
    {
        $administrators = User::query()
            ->whereIn('role', [UserRole::ADMINISTRATOR, UserRole::ROOT])
            ->get();

        $operational = User::query()
            ->where('role', $voucher->source_type === NursingSupplySourceType::WAREHOUSE
                ? UserRole::WAREHOUSE_MANAGER
                : UserRole::NURSE)
            ->whereHas('warehouses', fn ($query) => $query->whereKey($voucher->warehouse_id))
            ->when(
                $voucher->source_type === NursingSupplySourceType::CABINET,
                fn ($query) => $query->whereKeyNot($voucher->requested_by),
            )
            ->get();

        return $administrators->merge($operational)->unique('id')->values();
    }

    private function notifyRequester(NursingVoucher $voucher, string $kind, string $message): void
    {
        $voucher->requester->notify(new NursingVoucherActionRequired($voucher, $kind, $message));
    }
}
