<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class HospitalIntegrationException extends RuntimeException
{
    private function __construct(
        public readonly string $safeMessage,
        string $technicalMessage,
        ?Throwable $previous = null,
    ) {
        parent::__construct($technicalMessage, 0, $previous);
    }

    public static function authenticationFailed(): self
    {
        return new self(
            'No fue posible autenticar la conexión con el sistema de Hospitalización.',
            'Hospital API authentication failed.',
        );
    }

    public static function unavailable(?Throwable $previous = null): self
    {
        return new self(
            'El sistema de Hospitalización no está disponible en este momento.',
            'Hospital API is unavailable.',
            $previous,
        );
    }

    public static function invalidResponse(): self
    {
        return new self(
            'El sistema de Hospitalización devolvió una respuesta que no pudo procesarse.',
            'Hospital API response does not satisfy the active patient contract.',
        );
    }
}
