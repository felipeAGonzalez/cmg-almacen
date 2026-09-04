<?php

namespace App\Data;

use App\Enums\NursingSupplySourceType;
use App\Models\Cabinet;
use App\Models\Warehouse;

final readonly class NursingSupplySource
{
    public function __construct(
        public NursingSupplySourceType $type,
        public Warehouse $warehouse,
        public ?Cabinet $cabinet = null,
    ) {}
}
