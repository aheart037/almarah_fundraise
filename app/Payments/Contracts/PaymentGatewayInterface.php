<?php

declare(strict_types=1);

namespace App\Payments\Contracts;

use App\Payments\Dtos\PaymentRefundResult;
use App\Payments\Dtos\PaymentRegistrationResult;
use App\Payments\Dtos\PaymentRequest;
use App\Payments\Dtos\PaymentVerificationResult;

/**
 * Every gateway must implement the full lifecycle. There is no "assume
 * success" path anywhere in this contract: registration only produces a
 * redirect, and only a server-to-server verification can complete a payment.
 */
interface PaymentGatewayInterface
{
    public function code(): string;

    public function label(): string;

    public function isEnabled(): bool;

    /** @return 'sandbox'|'live' */
    public function environment(): string;

    /** True when the gateway credentials are present and usable. */
    public function isConfigured(): bool;

    public function supportsRefunds(): bool;

    /**
     * Create the payment at the provider and return where to send the donor.
     * Must never report success without a validated provider order/transaction.
     */
    public function register(PaymentRequest $request): PaymentRegistrationResult;

    /**
     * Server-to-server status check. This is the only thing that may complete
     * a donation.
     *
     * @param array<string,mixed> $context providerOrderId, providerTransactionId,
     *                                      merchantOrderId, expectedAmountMinor, expectedCurrency
     */
    public function verify(array $context): PaymentVerificationResult;

    /**
     * Refund a completed payment. Gateways without refund support return a
     * failing result with status 'rejected' rather than pretending success.
     */
    public function refund(array $context): PaymentRefundResult;

    /**
     * Where the donor should be sent after the provider page.
     * Used when building the registration request.
     */
    public function returnUrl(string $callbackState): string;
}
