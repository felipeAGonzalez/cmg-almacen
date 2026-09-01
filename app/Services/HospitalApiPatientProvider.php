<?php

namespace App\Services;

use App\Contracts\HospitalPatientProvider;
use App\Data\HospitalizedPatient;
use App\Exceptions\HospitalIntegrationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HospitalApiPatientProvider implements HospitalPatientProvider
{
    private const ACTIVE_PATIENTS_ENDPOINT = '/api/integrations/warehouse/active-patients';

    public function activePatients(): Collection
    {
        try {
            $response = Http::baseUrl(rtrim((string) config('hospital.url'), '/'))
                ->acceptJson()
                ->withToken((string) config('hospital.token'))
                ->timeout((int) config('hospital.timeout', 5))
                ->get(self::ACTIVE_PATIENTS_ENDPOINT);
        } catch (ConnectionException $exception) {
            Log::warning('Hospital API connection failed.', ['exception' => $exception::class]);

            throw HospitalIntegrationException::unavailable($exception);
        }

        if (in_array($response->status(), [401, 403], true)) {
            Log::warning('Hospital API authentication failed.', ['status' => $response->status()]);

            throw HospitalIntegrationException::authenticationFailed();
        }

        if ($response->failed()) {
            Log::warning('Hospital API request failed.', ['status' => $response->status()]);

            throw HospitalIntegrationException::unavailable();
        }

        $payload = $response->json();
        if (! is_array($payload) || ! array_key_exists('data', $payload) || ! is_array($payload['data'])) {
            $this->throwInvalidResponse();
        }

        return collect($payload['data'])->map(function (mixed $patient): HospitalizedPatient {
            if (! is_array($patient)) {
                $this->throwInvalidResponse();
            }

            foreach (['patient_id', 'hospitalization_id', 'patient_name', 'room_id', 'room_number'] as $field) {
                if (! array_key_exists($field, $patient) || ! is_scalar($patient[$field]) || trim((string) $patient[$field]) === '') {
                    $this->throwInvalidResponse();
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

    private function throwInvalidResponse(): never
    {
        Log::warning('Hospital API returned an invalid active patient payload.');

        throw HospitalIntegrationException::invalidResponse();
    }
}
