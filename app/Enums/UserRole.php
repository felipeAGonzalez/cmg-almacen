<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMINISTRATOR = 'administrator';
    case WAREHOUSE_MANAGER = 'warehouse_manager';
    case NURSE = 'nurse';
    case ROOT = 'root';
    case LEGACY_USER = 'user';

    public function label(): string
    {
        return match ($this) {
            self::ADMINISTRATOR => 'Administrador',
            self::WAREHOUSE_MANAGER => 'Almacenista',
            self::NURSE => 'Enfermero',
            self::ROOT => 'Root',
            self::LEGACY_USER => 'Usuario heredado',
        };
    }

    public function selectable(): bool
    {
        return match ($this) {
            self::ADMINISTRATOR, self::WAREHOUSE_MANAGER, self::NURSE => true,
            self::ROOT, self::LEGACY_USER => false,
        };
    }

    /**
     * @return list<self>
     */
    public static function selectableCases(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $role): bool => $role->selectable(),
        ));
    }
}
