<?php

namespace App\Rules;

use App\Contracts\HospitalNurseProvider;
use App\Exceptions\HospitalIntegrationException;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class HospitalNurseExists implements ValidationRule
{
    public function __construct(
        private readonly HospitalNurseProvider $provider,
        private readonly ?string $currentHospitalUserId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $exists = $this->provider->nurses()->contains(
                fn ($nurse): bool => $nurse->hospitalUserId === (string) $value,
            );
        } catch (HospitalIntegrationException) {
            if ($this->currentHospitalUserId !== null
                && hash_equals($this->currentHospitalUserId, (string) $value)) {
                return;
            }

            $fail('No fue posible validar la enfermera con el sistema de Hospitalización.');

            return;
        }

        if (! $exists) {
            $fail('La enfermera seleccionada no existe o ya no tiene el cargo Nurse en Hospitalización.');
        }
    }
}
