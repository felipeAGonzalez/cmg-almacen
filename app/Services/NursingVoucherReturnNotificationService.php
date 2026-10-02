<?php

namespace App\Services;

use App\Enums\NursingSupplySourceType;
use App\Enums\UserRole;
use App\Models\NursingVoucherReturn;
use App\Models\User;
use App\Notifications\NursingVoucherReturnNotification;
use Illuminate\Notifications\DatabaseNotification;

class NursingVoucherReturnNotificationService
{
    public const ACTION_REQUIRED = 'return_action_required';

    public function notifyRequested(NursingVoucherReturn $return): void
    {
        $return->loadMissing('voucher');
        $voucher = $return->voucher;
        $recipients = User::query()
            ->where(function ($query) use ($voucher): void {
                $query->whereIn('role', [UserRole::ADMINISTRATOR, UserRole::ROOT])
                    ->orWhere(function ($query) use ($voucher): void {
                        $query->where('role', $voucher->source_type === NursingSupplySourceType::WAREHOUSE
                            ? UserRole::WAREHOUSE_MANAGER
                            : UserRole::NURSE)
                            ->whereHas('warehouses', fn ($query) => $query->whereKey($voucher->warehouse_id));
                    });
            })
            ->whereKeyNot($return->requested_by)
            ->get();

        foreach ($recipients as $recipient) {
            $exists = $recipient->unreadNotifications()
                ->where('data->kind', self::ACTION_REQUIRED)
                ->where('data->nursing_voucher_return_id', $return->id)
                ->exists();
            if (! $exists) {
                $recipient->notify(new NursingVoucherReturnNotification(
                    $return,
                    self::ACTION_REQUIRED,
                    'Hay una devolución de Enfermería pendiente de recepción.',
                ));
            }
        }
    }

    public function notifyReceived(NursingVoucherReturn $return): void
    {
        $this->closeAction($return);
        $return->requester->notify(new NursingVoucherReturnNotification(
            $return,
            'return_received',
            'Tu devolución de Enfermería fue recibida.',
        ));
    }

    public function notifyRejected(NursingVoucherReturn $return): void
    {
        $this->closeAction($return);
        $return->requester->notify(new NursingVoucherReturnNotification(
            $return,
            'return_rejected',
            'Tu devolución de Enfermería fue rechazada.',
        ));
    }

    public function closeAction(NursingVoucherReturn $return): void
    {
        DatabaseNotification::query()
            ->whereNull('read_at')
            ->where('data->kind', self::ACTION_REQUIRED)
            ->where('data->nursing_voucher_return_id', $return->id)
            ->update(['read_at' => now(), 'updated_at' => now()]);
    }
}
