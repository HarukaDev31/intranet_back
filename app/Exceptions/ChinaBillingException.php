<?php

namespace App\Exceptions;

use RuntimeException;

class ChinaBillingException extends RuntimeException
{
    public function __construct(string $message, public int $status = 422)
    {
        parent::__construct($message);
    }
}
