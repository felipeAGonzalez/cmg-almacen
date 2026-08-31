<?php

namespace App\Enums;

enum InventoryOutboundReason: string
{
    case INTERNAL_CONSUMPTION = 'internal_consumption';
    case SHRINKAGE = 'shrinkage';
    case DAMAGE = 'damage';
    case SUPPLIER_RETURN = 'supplier_return';
    case ADJUSTMENT = 'adjustment';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::INTERNAL_CONSUMPTION => 'Consumo interno',
            self::SHRINKAGE => 'Merma',
            self::DAMAGE => 'Daño',
            self::SUPPLIER_RETURN => 'Devolución a proveedor',
            self::ADJUSTMENT => 'Ajuste',
            self::OTHER => 'Otro',
        };
    }
}
