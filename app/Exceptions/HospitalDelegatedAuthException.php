<?php

namespace App\Exceptions;

use RuntimeException;

class HospitalDelegatedAuthException extends RuntimeException
{
    public function __construct(
        public readonly string $reason = 'invalid_token',
        public readonly string $safeMessage = 'El acceso desde Hospitalización no es válido o ha expirado.',
    ) {
        parent::__construct('Hospital delegated authentication failed: '.$reason);
    }
}
