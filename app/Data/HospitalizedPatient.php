<?php

namespace App\Data;

/**
 * Normalized active hospitalization data from the hospital system.
 * Future nursing vouchers must persist every value as an immutable historical snapshot.
 * Snapshot fields: external_patient_id, external_hospitalization_id, patient_name,
 * external_room_id, and room_number.
 */
final readonly class HospitalizedPatient
{
    public function __construct(
        public string $externalPatientId,
        public string $externalHospitalizationId,
        public string $patientName,
        public string $externalRoomId,
        public string $roomNumber,
    ) {}
}
