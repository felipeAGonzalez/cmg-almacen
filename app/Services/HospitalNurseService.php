<?php

namespace App\Services;

use App\Contracts\HospitalNurseProvider;
use App\Data\HospitalNurse;
use App\Models\User;
use Illuminate\Support\Collection;

class HospitalNurseService
{
    public function __construct(private readonly HospitalNurseProvider $provider) {}

    /** @return Collection<int, HospitalNurse> */
    public function availableFor(?User $user = null): Collection
    {
        return $this->optionsFor($user)['available'];
    }

    /** @return array{available: Collection<int, HospitalNurse>, total: int} */
    public function optionsFor(?User $user = null): array
    {
        $linkedIds = User::query()
            ->whereNotNull('hospital_user_id')
            ->when($user, fn ($query) => $query->whereKeyNot($user->getKey()))
            ->pluck('hospital_user_id')
            ->map(fn (mixed $id): string => (string) $id);

        $hospitalNurses = $this->provider->nurses();

        return [
            'available' => $hospitalNurses
                ->reject(fn (HospitalNurse $nurse): bool => $linkedIds->contains($nurse->hospitalUserId))
                ->values(),
            'total' => $hospitalNurses->count(),
        ];
    }
}
