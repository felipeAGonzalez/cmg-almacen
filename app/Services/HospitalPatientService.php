<?php

namespace App\Services;

use App\Contracts\HospitalPatientProvider;
use App\Data\HospitalizedPatient;
use Illuminate\Support\Collection;

class HospitalPatientService
{
    public function __construct(private readonly HospitalPatientProvider $provider) {}

    /** @return Collection<int, HospitalizedPatient> */
    public function activePatients(): Collection
    {
        return $this->provider->activePatients()
            ->sort(function (HospitalizedPatient $left, HospitalizedPatient $right): int {
                return strnatcasecmp($left->roomNumber, $right->roomNumber)
                    ?: strcasecmp($left->patientName, $right->patientName);
            })
            ->values();
    }
}
