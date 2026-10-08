<?php

declare(strict_types=1);

namespace App\Payments\Dtos;

/**
 * Everything a gateway needs to register a payment. Provider-specific field
 * naming is the gateway's responsibility, never the caller's.
 */
final class PaymentRequest
{
    public function __construct(
        public readonly string $merchantOrderId,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly string $description,
        public readonly string $returnUrl,
        public readonly string $callbackUrl,
        public readonly string $idempotencyKey,
        public readonly ?string $customerName = null,
        public readonly ?string $customerEmail = null,
        public readonly ?string $customerIp = null,
        /** @var array<string,mixed> */
        public readonly array $metadata = []
    ) {
    }

    /** Decimal string for provider APIs. Conversion lives in Money only. */
    public function amountDecimal(): string
    {
        return \App\Core\Money::toProviderString($this->amountMinor);
    }
}
