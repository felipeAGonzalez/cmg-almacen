<?php

namespace App\Notifications;

use App\Models\NursingVoucher;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class NursingVoucherActionRequired extends Notification
{
    use Queueable;

    public function __construct(
        private readonly NursingVoucher $voucher,
        private readonly string $kind,
        private readonly string $message,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            ...$this->toArray($notifiable),
            'action_url' => route('nursing-vouchers.show', $this->voucher, absolute: false),
        ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'nursing_voucher_id' => $this->voucher->getKey(),
            'status' => $this->voucher->status->value,
            'source_type' => $this->voucher->source_type->value,
            'warehouse_id' => $this->voucher->warehouse_id,
            'source_cabinet_id' => $this->voucher->source_cabinet_id,
            'patient_name' => $this->voucher->patient_name,
            'room_number' => $this->voucher->room_number,
            'message' => $this->message,
            'route_name' => 'nursing-vouchers.show',
            'route_parameters' => ['nursingVoucher' => $this->voucher->getKey()],
        ];
    }
}
