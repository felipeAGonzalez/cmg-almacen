<?php

namespace App\Services;

use App\Data\NursingSupplySource;
use App\Enums\NursingSupplySourceType;
use App\Enums\UserRole;
use App\Exceptions\NursingSupplyConfigurationException;
use App\Models\User;
use Carbon\CarbonInterface;

class NursingSupplySourceService
{
    public function __construct(
        private readonly OperationalScheduleService $schedule,
    ) {}

    public function resolve(User $nurse, ?CarbonInterface $dateTime = null): NursingSupplySource
    {
        if ($nurse->role !== UserRole::NURSE) {
            throw new NursingSupplyConfigurationException('El usuario no tiene el cargo de enfermera.');
        }

        $warehouse = $nurse->warehouseForNursing();

        if (! $warehouse) {
            throw new NursingSupplyConfigurationException('La enfermera no tiene un almacén asignado.');
        }

        if ($this->schedule->isWarehouseAvailableAt($dateTime ?? now())) {
            return new NursingSupplySource(NursingSupplySourceType::WAREHOUSE, $warehouse);
        }

        return new NursingSupplySource(
            NursingSupplySourceType::CABINET,
            $warehouse,
            $warehouse->defaultNursingCabinetOrFail(),
        );
    }
}
