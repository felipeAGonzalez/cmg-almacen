<?php

namespace App\Enums;

enum InventoryAdjustmentReason: string
{
    case PHYSICAL_COUNT = 'physical_count';
    case COUNTING_ERROR = 'counting_error';
    case SYSTEM_CORRECTION = 'system_correction';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PHYSICAL_COUNT => 'Conteo físico',
            self::COUNTING_ERROR => 'Error de conteo',
            self::SYSTEM_CORRECTION => 'Corrección del sistema',
            self::OTHER => 'Otro',
        };
    }
}
