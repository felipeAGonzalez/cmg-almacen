<?php

namespace App\Data;

final readonly class HospitalNurse
{
    public function __construct(
        public string $hospitalUserId,
        public string $name,
        public string $email,
    ) {}
}
