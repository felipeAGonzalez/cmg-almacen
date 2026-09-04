<?php

namespace App\Enums;

enum NursingVoucherStatus: string
{
    case PENDING = 'pending';
    case PARTIALLY_SUPPLIED = 'partially_supplied';
    case SUPPLIED = 'supplied';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';

    public function badgeClass(): string
    {
        return match ($this) {
            self::PENDING => 'text-bg-warning',
            self::PARTIALLY_SUPPLIED => 'text-bg-info',
            self::SUPPLIED => 'text-bg-success',
            self::REJECTED => 'text-bg-danger',
            self::CANCELLED => 'text-bg-secondary',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendiente',
            self::PARTIALLY_SUPPLIED => 'Parcialmente surtido',
            self::SUPPLIED => 'Surtido',
            self::REJECTED => 'Rechazado',
            self::CANCELLED => 'Cancelado',
        };
    }
}
