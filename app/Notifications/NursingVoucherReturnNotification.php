<?php

namespace App\Notifications;

use App\Models\NursingVoucherReturn;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class NursingVoucherReturnNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly NursingVoucherReturn $return,
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
            'action_url' => route('nursing-vouchers.show', $this->return->nursing_voucher_id, absolute: false),
        ]);
    }

    public function toArray(object $notifiable): array
    {
        $voucher = $this->return->voucher;

        return [
            'kind' => $this->kind,
            'nursing_voucher_id' => $voucher->id,
            'nursing_voucher_return_id' => $this->return->id,
            'status' => $this->return->status->value,
            'source_type' => $voucher->source_type->value,
            'warehouse_id' => $voucher->warehouse_id,
            'source_cabinet_id' => $voucher->source_cabinet_id,
            'patient_name' => $voucher->patient_name,
            'room_number' => $voucher->room_number,
            'message' => $this->message,
            'route_name' => 'nursing-vouchers.show',
            'route_parameters' => ['nursingVoucher' => $voucher->id],
        ];
    }
}
