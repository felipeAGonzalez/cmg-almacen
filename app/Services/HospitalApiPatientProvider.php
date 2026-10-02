<?php

namespace App\Services;

use App\Contracts\HospitalPatientProvider;
use App\Data\HospitalizedPatient;
use Illuminate\Support\Collection;

class HospitalApiPatientProvider implements HospitalPatientProvider
{
    private const ACTIVE_PATIENTS_ENDPOINT = '/api/integrations/warehouse/active-patients';

    public function __construct(private readonly HospitalApiClient $client) {}

    public function activePatients(): Collection
    {
        return collect($this->client->getData(self::ACTIVE_PATIENTS_ENDPOINT, 'active_patients'))->map(function (mixed $patient): HospitalizedPatient {
            if (! is_array($patient)) {
                $this->client->throwInvalidResponse('active_patients');
            }

            foreach (['patient_id', 'hospitalization_id', 'patient_name', 'room_id', 'room_number'] as $field) {
                if (! array_key_exists($field, $patient) || ! is_scalar($patient[$field]) || trim((string) $patient[$field]) === '') {
                    $this->client->throwInvalidResponse('active_patients');
                }
            }

            return new HospitalizedPatient(
                externalPatientId: trim((string) $patient['patient_id']),
                externalHospitalizationId: trim((string) $patient['hospitalization_id']),
                patientName: trim((string) $patient['patient_name']),
                externalRoomId: trim((string) $patient['room_id']),
                roomNumber: trim((string) $patient['room_number']),
            );
        })->values();
    }
}
