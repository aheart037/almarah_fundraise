<?php

declare(strict_types=1);

namespace App\Payments\Dtos;

final class PaymentRegistrationResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly string $gateway,
        public readonly string $merchantOrderId,
        public readonly ?string $providerTransactionId = null,
        public readonly ?string $providerOrderId = null,
        public readonly ?string $providerUniqueId = null,
        /** Provider-hosted payment page the payer must be sent to, when the provider returns one. */
        public readonly ?string $providerPaymentPageUrl = null,
        /** Internal bridge URL or provider-hosted page to send the donor to. */
        public readonly ?string $redirectUrl = null,
        public readonly string $redirectMethod = 'GET',
        /** @var array<string,string> fields the redirect form must submit */
        public readonly array $postFields = [],
        public readonly ?string $providerCode = null,
        public readonly ?string $providerMessage = null,
        /** @var array<string,mixed> */
        public readonly array $safeResponse = []
    ) {
    }

    public static function failure(string $gateway, string $merchantOrderId, string $message, ?string $code = null): self
    {
        return new self(
            ok: false,
            gateway: $gateway,
            merchantOrderId: $merchantOrderId,
            providerCode: $code,
            providerMessage: $message
        );
    }

    public function hasRedirect(): bool
    {
        return $this->ok && $this->redirectUrl !== null && $this->redirectUrl !== '';
    }
}
