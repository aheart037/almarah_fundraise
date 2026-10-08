<?php

declare(strict_types=1);

namespace App\Payments\Dtos;

/**
 * Result of a server-to-server status verification.
 *
 * `amountMinor` / `currency` are null when the provider does not echo them;
 * a null value is never treated as a match and never as a mismatch — the
 * caller decides using the gateway's documented behaviour.
 */
final class PaymentVerificationResult
{
    public function __construct(
        public readonly string $gateway,
        /** Platform status: pending|processing|completed|failed|cancelled|refunded */
        public readonly string $status,
        public readonly bool $verified,
        public readonly ?string $providerStatus = null,
        public readonly ?string $providerCode = null,
        public readonly ?string $providerMessage = null,
        public readonly ?int $amountMinor = null,
        public readonly ?string $currency = null,
        public readonly ?string $providerTransactionId = null,
        public readonly ?string $providerOrderId = null,
        /** @var array<string,mixed> */
        public readonly array $safeResponse = []
    ) {
    }

    public function isCompleted(): bool
    {
        return $this->verified && $this->status === 'completed';
    }

    /** True when the provider is still deciding; requires reconciliation later. */
    public function isProcessing(): bool
    {
        return in_array($this->status, ['pending', 'processing'], true);
    }
}
