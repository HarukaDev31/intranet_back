<?php

namespace App\Exceptions;

use RuntimeException;

class PagoProveedorException extends RuntimeException
{
    public function __construct(string $message, public int $status = 422)
    {
        parent::__construct($message);
    }
}
