<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class HttpException extends RuntimeException
{
    public function __construct(private int $statusCode, string $message = '', private array $headers = [])
    {
        parent::__construct($message !== '' ? $message : self::defaultMessage($statusCode), $statusCode);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /** @return array<string,string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public static function defaultMessage(int $status): string
    {
        return match ($status) {
            400 => 'The request could not be understood.',
            401 => 'You need to sign in to continue.',
            403 => 'You are not allowed to perform that action.',
            404 => 'We could not find the page you were looking for.',
            405 => 'That action is not allowed on this page.',
            419 => 'Your session expired. Please try again.',
            422 => 'The submitted data was not valid.',
            429 => 'Too many attempts. Please wait and try again.',
            500 => 'Something went wrong on our side.',
            503 => 'The service is temporarily unavailable.',
            default => 'Request failed.',
        };
    }
}
