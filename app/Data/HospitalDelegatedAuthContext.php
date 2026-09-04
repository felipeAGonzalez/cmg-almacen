<?php

namespace App\Data;

final readonly class HospitalDelegatedAuthContext
{
    public function __construct(
        public string $hospitalUserId,
        public string $patientId,
        public string $hospitalizationId,
        public string $roomId,
        public string $roomNumber,
        public int $issuedAt,
        public int $expiresAt,
    ) {}

    /** @return array{patient_id: string, hospitalization_id: string, room_id: string, room_number: string} */
    public function sessionData(): array
    {
        return [
            'patient_id' => $this->patientId,
            'hospitalization_id' => $this->hospitalizationId,
            'room_id' => $this->roomId,
            'room_number' => $this->roomNumber,
        ];
    }
}
