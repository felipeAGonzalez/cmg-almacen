<?php

namespace App\Enums;

enum NursingSupplySourceType: string
{
    case WAREHOUSE = 'warehouse';
    case CABINET = 'cabinet';

    public function label(): string
    {
        return match ($this) {
            self::WAREHOUSE => 'Almacén',
            self::CABINET => 'Gabinete',
        };
    }
}
