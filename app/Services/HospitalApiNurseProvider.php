<?php

namespace App\Services;

use App\Contracts\HospitalNurseProvider;
use App\Data\HospitalNurse;
use Illuminate\Support\Collection;

class HospitalApiNurseProvider implements HospitalNurseProvider
{
    private const NURSES_ENDPOINT = '/api/integrations/warehouse/nurses';

    public function __construct(private readonly HospitalApiClient $client) {}

    public function nurses(): Collection
    {
        return collect($this->client->getData(self::NURSES_ENDPOINT, 'nurses'))
            ->map(function (mixed $nurse): HospitalNurse {
                if (! is_array($nurse)) {
                    $this->client->throwInvalidResponse('nurses');
                }

                foreach (['user_id', 'name', 'email'] as $field) {
                    if (! array_key_exists($field, $nurse)
                        || ! is_scalar($nurse[$field])
                        || trim((string) $nurse[$field]) === '') {
                        $this->client->throwInvalidResponse('nurses');
                    }
                }

                return new HospitalNurse(
                    hospitalUserId: trim((string) $nurse['user_id']),
                    name: trim((string) $nurse['name']),
                    email: trim((string) $nurse['email']),
                );
            })
            ->values();
    }
}
