<?php

namespace App\Enums;

enum ManualVoucherRequestingArea: string
{
    case NURSING_AREA = 'nursing_area';
    case PAMEDIC = 'pamedic';

    public function label(): string
    {
        return match ($this) {
            self::NURSING_AREA => 'Área de Enfermería',
            self::PAMEDIC => 'Pamedic',
        };
    }
}
