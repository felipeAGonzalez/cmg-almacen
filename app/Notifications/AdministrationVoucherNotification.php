<?php

namespace App\Notifications;

use App\Models\AdministrationVoucher;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AdministrationVoucherNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly AdministrationVoucher $voucher, private readonly string $kind, private readonly string $message) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'administration_voucher_id' => $this->voucher->getKey(),
            'status' => $this->voucher->status->value,
            'warehouse_id' => $this->voucher->warehouse_id,
            'cabinet_id' => $this->voucher->cabinet_id,
            'message' => $this->message,
            'route_name' => 'administration-vouchers.show',
            'route_parameters' => ['administrationVoucher' => $this->voucher->getKey()],
        ];
    }
}
