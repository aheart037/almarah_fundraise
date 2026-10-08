<?php

declare(strict_types=1);

namespace App\Payments\Meezan;

use App\Core\Logger;
use App\Core\Money;
use App\Core\Str;
use App\Payments\Contracts\PaymentGatewayInterface;
use App\Payments\Dtos\PaymentRefundResult;
use App\Payments\Dtos\PaymentRegistrationResult;
use App\Payments\Dtos\PaymentRequest;
use App\Payments\Dtos\PaymentVerificationResult;
use App\Payments\Exceptions\PaymentGatewayException;
use App\Payments\PaymentStatusMapper;

/**
 * Meezan Bank hosted payment gateway.
 *
 * Registration returns a hosted formUrl. The donor pays there; the return trip
 * carries only an order id, which is then re-checked server-to-server through
 * getOrderStatus.do. The browser is never trusted for status.
 */
final class MeezanGateway implements PaymentGatewayInterface
{
    public function __construct(
        private MeezanApiClient $api,
        private PaymentStatusMapper $statusMapper,
        private Logger $logger,
        private array $config,
        private string $baseUrl = ''
    ) {
    }

    public function code(): string
    {
        return 'meezan';
    }

    public function label(): string
    {
        return (string) ($this->config['label'] ?? 'Meezan Bank');
    }

    public function isEnabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? false);
    }

    public function environment(): string
    {
        $env = (string) ($this->config['environment'] ?? 'sandbox');
        return in_array($env, ['sandbox', 'live'], true) ? $env : 'sandbox';
    }

    public function isConfigured(): bool
    {
        return (string) ($this->config['username'] ?? '') !== ''
            && (string) ($this->config['password'] ?? '') !== ''
            && (string) ($this->config['sandbox_url'] ?? '') !== ''
            && (string) ($this->config['live_url'] ?? '') !== '';
    }

    public function supportsRefunds(): bool
    {
        // Meezan refund support depends on the merchant agreement; it is only
        // attempted when an administrator has explicitly enabled it.
        return (bool) ($this->config['refund_enabled'] ?? false);
    }

    public function returnUrl(string $callbackState): string
    {
        return rtrim($this->baseUrl, '/') . '/payments/meezan/return?state=' . rawurlencode($callbackState);
    }

    /**
     * Ask the provider to authenticate against a deliberately unknown order.
     *
     * This creates no transaction and moves no money: a valid merchant account
     * answers "order not found", while bad credentials are refused outright.
     * Meezan's legacy API gives us no separate ping endpoint, so this is the
     * closest honest check available.
     */
    public function credentialsValid(): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $response = $this->api->orderStatus('000000');

        $message = mb_strtolower((string) ($response['ErrorMessage'] ?? $response['errorMessage'] ?? ''));

        foreach (['authentication', 'invalid user', 'invalid password', 'access denied', 'not authorized', 'merchant not found'] as $needle) {
            if ($message !== '' && str_contains($message, $needle)) {
                $this->logger->security('Meezan credential check rejected', ['reason' => $needle]);
                return false;
            }
        }

        return true;
    }

    public function register(PaymentRequest $request): PaymentRegistrationResult
    {
        if (!$this->isConfigured()) {
            throw new PaymentGatewayException('Meezan is not fully configured.', 'meezan', 'not_configured');
        }

        $currencyName = (string) ($this->config['currency_name'] ?? 'PKR');
        $currencyCode = (string) ($this->config['currency_code'] ?? '586');

        if (strtoupper($request->currency) !== strtoupper($currencyName)) {
            throw new PaymentGatewayException(
                'Currency mismatch: Meezan is configured for ' . $currencyName . ' but the donation is in ' . $request->currency . '.',
                'meezan',
                'currency_mismatch'
            );
        }

        if ($request->amountMinor <= 0) {
            throw new PaymentGatewayException('Donation amount must be positive.', 'meezan', 'invalid_amount');
        }

        $fields = [
            'amount'      => $request->amountDecimal(),
            'currency'    => $currencyCode,
            'orderNumber' => $request->merchantOrderId,
            'returnUrl'   => $request->returnUrl,
            'orderId'     => $request->merchantOrderId,
            'description' => Str::limit($request->description, 100, ''),
        ];

        if ((string) ($this->config['merchant_id'] ?? '') !== '') {
            $fields['merchantId'] = (string) $this->config['merchant_id'];
        }

        $response = $this->api->register($fields);

        $errorCode = isset($response['errorCode']) ? (string) $response['errorCode'] : null;
        $errorMessage = (string) ($response['errorMessage'] ?? '');
        $formUrl = (string) ($response['formUrl'] ?? $response['form_url'] ?? '');
        $providerOrderId = (string) ($response['orderId'] ?? $response['order_id'] ?? '');

        // A provider error code that is present and non-zero is a failure.
        if ($errorCode !== null && $errorCode !== '' && $errorCode !== '0') {
            $this->logger->payment('Meezan registration rejected', [
                'error_code' => $errorCode,
                'order'      => $request->merchantOrderId,
            ]);

            return PaymentRegistrationResult::failure(
                'meezan',
                $request->merchantOrderId,
                $errorMessage !== '' ? $errorMessage : 'Meezan rejected the payment registration.',
                $errorCode
            );
        }

        if ($providerOrderId === '') {
            return PaymentRegistrationResult::failure(
                'meezan',
                $request->merchantOrderId,
                'Meezan did not return a provider order id.',
                $errorCode
            );
        }

        $redirect = $this->validatePaymentFormUrl($formUrl);

        $this->logger->payment('Meezan registration succeeded', [
            'order'      => $request->merchantOrderId,
            'provider'   => $providerOrderId,
            'environment'=> $this->environment(),
        ]);

        return new PaymentRegistrationResult(
            ok: true,
            gateway: 'meezan',
            merchantOrderId: $request->merchantOrderId,
            providerOrderId: $providerOrderId,
            providerTransactionId: null,
            providerUniqueId: null,
            redirectUrl: $redirect,
            redirectMethod: 'GET',
            postFields: [],
            providerCode: $errorCode,
            providerMessage: $errorMessage !== '' ? $errorMessage : null,
            safeResponse: $this->safeSubset($response, ['orderId', 'errorCode', 'errorMessage', 'OrderStatus'])
        );
    }

    /**
     * Validate the hosted payment page URL: HTTPS, parseable, and on an
     * allowlisted Meezan host. A hijacked formUrl is the main redirect risk in
     * this flow.
     */
    private function validatePaymentFormUrl(string $formUrl): string
    {
        if (!Str::isHttpsUrl($formUrl)) {
            $this->logger->security('Meezan returned a non-HTTPS payment form URL', ['gateway' => 'meezan']);
            throw new PaymentGatewayException(
                'Meezan returned an invalid (non-HTTPS) payment page URL.',
                'meezan',
                'invalid_payment_url'
            );
        }

        $host = Str::host($formUrl) ?? '';
        $allowed = (array) ($this->config['allowed_hosts'] ?? []);

        $isAllowed = false;
        foreach ($allowed as $allowedHost) {
            $allowedHost = strtolower(trim((string) $allowedHost));
            if ($allowedHost === '') {
                continue;
            }
            if ($host === $allowedHost || str_ends_with($host, '.' . $allowedHost)) {
                $isAllowed = true;
                break;
            }
        }

        if (!$isAllowed) {
            $this->logger->security('Meezan payment form URL host is not allowlisted', [
                'host' => $host,
            ]);
            throw new PaymentGatewayException(
                'Meezan returned a payment page on an unrecognised host.',
                'meezan',
                'untrusted_payment_url'
            );
        }

        return $formUrl;
    }

    /**
     * @param array<string,mixed> $context
     */
    public function verify(array $context): PaymentVerificationResult
    {
        $providerOrderId = (string) ($context['providerOrderId'] ?? '');
        $expectedAmountMinor = $context['expectedAmountMinor'] ?? null;
        $expectedCurrency = $context['expectedCurrency'] ?? null;

        if ($providerOrderId === '') {
            throw new PaymentGatewayException(
                'Cannot verify a Meezan payment without a provider order id.',
                'meezan',
                'missing_order_id'
            );
        }

        $response = $this->api->orderStatus($providerOrderId);

        $orderStatus = isset($response['OrderStatus'])
            ? (string) $response['OrderStatus']
            : (isset($response['orderStatus']) ? (string) $response['orderStatus'] : null);

        $errorCode = isset($response['ErrorCode']) ? (string) $response['ErrorCode']
            : (isset($response['errorCode']) ? (string) $response['errorCode'] : null);

        $errorMessage = (string) ($response['ErrorMessage'] ?? $response['errorMessage'] ?? '');

        $status = $this->statusMapper->mapMeezan($orderStatus);

        // Amount / currency echoed by the provider must match what we stored.
        $providerAmountMinor = null;
        if (isset($response['Amount']) && is_numeric($response['Amount'])) {
            try {
                $providerAmountMinor = Money::fromProviderString((string) $response['Amount']);
            } catch (\Throwable $e) {
                $providerAmountMinor = null;
            }
        }

        $providerCurrency = isset($response['Currency']) ? strtoupper((string) $response['Currency']) : null;

        if ($expectedAmountMinor !== null && $providerAmountMinor !== null && (int) $expectedAmountMinor !== $providerAmountMinor) {
            $this->logger->security('Meezan amount mismatch on verification', [
                'order'    => $providerOrderId,
                'expected' => (int) $expectedAmountMinor,
                'received' => $providerAmountMinor,
            ]);

            return new PaymentVerificationResult(
                gateway: 'meezan',
                status: 'processing',
                verified: false,
                providerStatus: $orderStatus,
                providerCode: $errorCode,
                providerMessage: 'Amount reported by the provider does not match the donation.',
                providerOrderId: $providerOrderId,
                safeResponse: $this->safeSubset($response, ['OrderStatus', 'ErrorCode', 'ErrorMessage'])
            );
        }

        if ($expectedCurrency !== null && $providerCurrency !== null && strtoupper((string) $expectedCurrency) !== $providerCurrency) {
            $this->logger->security('Meezan currency mismatch on verification', [
                'order'    => $providerOrderId,
                'expected' => (string) $expectedCurrency,
                'received' => $providerCurrency,
            ]);

            return new PaymentVerificationResult(
                gateway: 'meezan',
                status: 'processing',
                verified: false,
                providerStatus: $orderStatus,
                providerCode: $errorCode,
                providerMessage: 'Currency reported by the provider does not match the donation.',
                providerOrderId: $providerOrderId,
                safeResponse: $this->safeSubset($response, ['OrderStatus', 'ErrorCode', 'ErrorMessage'])
            );
        }

        if ($orderStatus === null) {
            return new PaymentVerificationResult(
                gateway: 'meezan',
                status: 'processing',
                verified: false,
                providerCode: $errorCode,
                providerMessage: $errorMessage !== '' ? $errorMessage : 'Meezan did not report an order status.',
                providerOrderId: $providerOrderId,
                safeResponse: $this->safeSubset($response, ['ErrorCode', 'ErrorMessage'])
            );
        }

        return new PaymentVerificationResult(
            gateway: 'meezan',
            status: $status,
            verified: true,
            providerStatus: $orderStatus,
            providerCode: $errorCode,
            providerMessage: $errorMessage !== '' ? $errorMessage : null,
            amountMinor: $providerAmountMinor,
            currency: $providerCurrency,
            providerOrderId: isset($response['orderId']) ? (string) $response['orderId'] : $providerOrderId,
            safeResponse: $this->safeSubset($response, ['OrderStatus', 'orderId', 'ErrorCode', 'ErrorMessage'])
        );
    }

    /**
     * @param array<string,mixed> $context
     */
    public function refund(array $context): PaymentRefundResult
    {
        if (!$this->supportsRefunds()) {
            return new PaymentRefundResult(
                ok: false,
                gateway: 'meezan',
                status: 'rejected',
                providerMessage: 'Meezan refunds are not enabled for this merchant account. Confirm refund support with Meezan and enable it in payment settings.'
            );
        }

        $providerOrderId = (string) ($context['providerOrderId'] ?? '');
        $amountMinor = (int) ($context['amountMinor'] ?? 0);

        if ($providerOrderId === '' || $amountMinor <= 0) {
            return new PaymentRefundResult(
                ok: false,
                gateway: 'meezan',
                status: 'rejected',
                providerMessage: 'A provider order id and a positive amount are required to refund.'
            );
        }

        try {
            $response = $this->api->refund([
                'orderId' => $providerOrderId,
                'amount'  => Money::toProviderString($amountMinor),
                'currency'=> (string) ($this->config['currency_code'] ?? '586'),
            ]);
        } catch (PaymentGatewayException $e) {
            return new PaymentRefundResult(
                ok: false,
                gateway: 'meezan',
                status: 'failed',
                providerMessage: $e->getMessage()
            );
        }

        $errorCode = isset($response['errorCode']) ? (string) $response['errorCode'] : null;
        $errorMessage = (string) ($response['errorMessage'] ?? '');

        $ok = $errorCode === null || $errorCode === '' || $errorCode === '0';

        return new PaymentRefundResult(
            ok: $ok,
            gateway: 'meezan',
            status: $ok ? 'completed' : 'rejected',
            providerCode: $errorCode,
            providerMessage: $errorMessage !== '' ? $errorMessage : null,
            amountMinor: $amountMinor,
            safeResponse: $this->safeSubset($response, ['errorCode', 'errorMessage'])
        );
    }

    /**
     * Keep only fields that are safe to persist for reconciliation.
     *
     * @param array<string,mixed> $response
     * @param array<int,string>   $keys
     * @return array<string,mixed>
     */
    private function safeSubset(array $response, array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $response) && (is_scalar($response[$key]) || $response[$key] === null)) {
                $out[$key] = $response[$key];
            }
        }
        return $out;
    }
}
