<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class ValidationException extends RuntimeException
{
    /** @param array<string,string> $errors */
    public function __construct(private array $errors, string $message = 'The submitted data was not valid.')
    {
        parent::__construct($message, 422);
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $this->errors;
    }
}
