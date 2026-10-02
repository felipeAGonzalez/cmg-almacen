<?php

namespace App\Enums;

enum NursingVoucherReturnStatus: string
{
    case PENDING = 'pending';
    case RECEIVED = 'received';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendiente de recepción',
            self::RECEIVED => 'Recibida',
            self::REJECTED => 'Rechazada',
            self::CANCELLED => 'Cancelada',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::PENDING => 'text-bg-warning',
            self::RECEIVED => 'text-bg-success',
            self::REJECTED => 'text-bg-danger',
            self::CANCELLED => 'text-bg-secondary',
        };
    }
}
