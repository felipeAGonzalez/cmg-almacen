<?php

namespace App\Services;

use App\Exceptions\HospitalIntegrationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HospitalApiClient
{
    /** @return array<int, mixed> */
    public function getData(string $endpoint, string $resource): array
    {
        try {
            $response = Http::baseUrl(rtrim((string) config('hospital.url'), '/'))
                ->acceptJson()
                ->withToken((string) config('hospital.token'))
                ->timeout((int) config('hospital.timeout', 5))
                ->get($endpoint);
        } catch (ConnectionException $exception) {
            Log::warning('Hospital API connection failed.', [
                'resource' => $resource,
                'exception' => $exception::class,
            ]);

            throw HospitalIntegrationException::unavailable($exception);
        }

        if (in_array($response->status(), [401, 403], true)) {
            Log::warning('Hospital API authentication failed.', [
                'resource' => $resource,
                'status' => $response->status(),
            ]);

            throw HospitalIntegrationException::authenticationFailed();
        }

        if ($response->failed()) {
            Log::warning('Hospital API request failed.', [
                'resource' => $resource,
                'status' => $response->status(),
            ]);

            throw HospitalIntegrationException::unavailable();
        }

        $payload = $response->json();
        if (! is_array($payload) || ! array_key_exists('data', $payload) || ! is_array($payload['data'])) {
            $this->throwInvalidResponse($resource);
        }

        return $payload['data'];
    }

    public function throwInvalidResponse(string $resource): never
    {
        Log::warning('Hospital API returned an invalid payload.', ['resource' => $resource]);

        throw HospitalIntegrationException::invalidResponse();
    }
}
