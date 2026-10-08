<?php

declare(strict_types=1);

namespace App\Payments\Dtos;

final class PaymentRefundResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly string $gateway,
        public readonly string $status,
        public readonly ?string $providerCode = null,
        public readonly ?string $providerMessage = null,
        public readonly ?int $amountMinor = null,
        /** @var array<string,mixed> */
        public readonly array $safeResponse = []
    ) {
    }

    public function isCompleted(): bool
    {
        return $this->ok && $this->status === 'completed';
    }
}
