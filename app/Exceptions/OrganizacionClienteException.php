<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Error de validación al guardar una organización cliente (mensaje en español para el usuario).
 */
class OrganizacionClienteException extends RuntimeException
{
    public function __construct(string $message, public int $status = 422)
    {
        parent::__construct($message);
    }
}
