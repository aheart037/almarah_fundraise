<?php

declare(strict_types=1);

namespace App\Payments\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Raised for any gateway-level failure. Messages are safe to show in logs and
 * in the admin UI: they never contain credentials or raw provider payloads.
 */
class PaymentGatewayException extends RuntimeException
{
    public function __construct(
        string $message,
        private string $gateway = '',
        private string $reason = 'gateway_error',
        private ?int $providerCode = null,
        private array $safeContext = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function gateway(): string
    {
        return $this->gateway;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function providerCode(): ?int
    {
        return $this->providerCode;
    }

    /** @return array<string,mixed> */
    public function safeContext(): array
    {
        return $this->safeContext;
    }
}
