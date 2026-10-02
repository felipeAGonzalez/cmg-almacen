<?php

namespace App\Contracts;

use App\Data\HospitalNurse;
use Illuminate\Support\Collection;

interface HospitalNurseProvider
{
    /** @return Collection<int, HospitalNurse> */
    public function nurses(): Collection;
}
