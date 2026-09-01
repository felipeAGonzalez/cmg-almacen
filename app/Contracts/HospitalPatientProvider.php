<?php

namespace App\Contracts;

use App\Data\HospitalizedPatient;
use Illuminate\Support\Collection;

interface HospitalPatientProvider
{
    /** @return Collection<int, HospitalizedPatient> */
    public function activePatients(): Collection;
}
