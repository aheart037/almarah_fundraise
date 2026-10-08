<?php

declare(strict_types=1);

namespace App\Payments\Etisalat;

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
 * Etisalat / UBL EPG gateway.
 *
 * Flow: Registration (server-to-server) → internal bridge that auto-POSTs the
 * stored TransactionID to the hosted payment page → donor returns → the
 * callback triggers Finalization, which is the only thing that may complete
 * the donation. Provider codes returned to the browser are ignored entirely.
 */
final class EtisalatGateway implements PaymentGatewayInterface
{
    public function __construct(
        private EtisalatApiClient $api,
        private PaymentStatusMapper $statusMapper,
        private Logger $logger,
        private array $config,
        private string $baseUrl = ''
    ) {
    }

    public function code(): string
    {
        return 'etisalat';
    }

    public function label(): string
    {
        return (string) ($this->config['label'] ?? 'Etisalat / UBL EPG');
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
        return (string) ($this->config['customer'] ?? '') !== ''
            && (string) ($this->config['username'] ?? '') !== ''
            && (string) ($this->config['password'] ?? '') !== ''
            && (string) ($this->config['sandbox_url'] ?? '') !== ''
            && (string) ($this->config['live_url'] ?? '') !== '';
    }

    public function supportsRefunds(): bool
    {
        return true; // Documented in the EPG REST guide.
    }

    public function returnUrl(string $callbackState): string
    {
        return rtrim($this->baseUrl, '/') . '/payments/etisalat/return?state=' . rawurlencode($callbackState);
    }

    public function register(PaymentRequest $request): PaymentRegistrationResult
    {
        if (!$this->isConfigured()) {
            throw new PaymentGatewayException('Etisalat is not fully configured.', 'etisalat', 'not_configured');
        }

        $currency = strtoupper((string) ($this->config['currency'] ?? 'PKR'));
        if (strtoupper($request->currency) !== $currency) {
            throw new PaymentGatewayException(
                'Currency mismatch: Etisalat is configured for ' . $currency . ' but the donation is in ' . $request->currency . '.',
                'etisalat',
                'currency_mismatch'
            );
        }

        if ($request->amountMinor <= 0) {
            throw new PaymentGatewayException('Donation amount must be positive.', 'etisalat', 'invalid_amount');
        }

        $block = [
            'Customer'        => (string) ($this->config['customer'] ?? ''),
            'Channel'         => 'Web',
            'Amount'          => $request->amountDecimal(),
            'Currency'        => $currency,
            'OrderID'         => $request->merchantOrderId,
            'OrderName'       => 'Donation',
            'OrderInfo'       => Str::limit($request->description, 100, ''),
            'TransactionHint' => 'CPT:Y;VCC:Y;',
            'ReturnPath'      => $request->returnUrl,
            'UserName'        => (string) ($this->config['username'] ?? ''),
            'Password'        => (string) ($this->config['password'] ?? ''),
        ];

        if ((string) ($this->config['store'] ?? '') !== '') {
            $block['Store'] = (string) $this->config['store'];
        }
        if ((string) ($this->config['terminal'] ?? '') !== '') {
            $block['Terminal'] = (string) $this->config['terminal'];
        }

        $response = $this->api->send(EtisalatApiClient::OP_REGISTRATION, ['Registration' => $block]);
        $extracted = $this->api->extract('Registration', $response);

        $code = $extracted['code'];
        $message = $extracted['message'];
        $body = $extracted['block'];

        if ($code !== '0') {
            $this->logger->payment('Etisalat registration rejected', [
                'order' => $request->merchantOrderId,
                'code'  => $code,
            ]);

            return PaymentRegistrationResult::failure(
                'etisalat',
                $request->merchantOrderId,
                $message !== null && $message !== '' ? $message : 'Etisalat rejected the payment registration.',
                $code
            );
        }

        $transactionId = (string) ($body['TransactionID'] ?? $body['TransactionId'] ?? '');
        if ($transactionId === '') {
            return PaymentRegistrationResult::failure(
                'etisalat',
                $request->merchantOrderId,
                'Etisalat did not return a TransactionID.',
                $code
            );
        }

        $paymentPage = (string) ($body['PaymentPage'] ?? $body['PaymentPortal'] ?? '');
        $this->validatePaymentPageUrl($paymentPage);

        $uniqueId = isset($body['UniqueID']) ? (string) $body['UniqueID'] : null;

        // The donor never sees the EPG URL directly: they are sent to our own
        // bridge, which auto-submits the stored TransactionID. This keeps the
        // value under our control and satisfies the hosted-POST requirement.
        $bridgeUrl = rtrim($this->baseUrl, '/') . '/payments/etisalat/bridge/' . rawurlencode($transactionId);

        $this->logger->payment('Etisalat registration succeeded', [
            'order'       => $request->merchantOrderId,
            'transaction' => $transactionId,
            'environment' => $this->environment(),
        ]);

        return new PaymentRegistrationResult(
            ok: true,
            gateway: 'etisalat',
            merchantOrderId: $request->merchantOrderId,
            providerTransactionId: $transactionId,
            providerOrderId: $request->merchantOrderId,
            providerUniqueId: $uniqueId,
            providerPaymentPageUrl: $paymentPage,
            redirectUrl: $bridgeUrl,
            redirectMethod: 'GET',
            postFields: [],
            providerCode: $code,
            providerMessage: $message,
            safeResponse: $this->safeSubset($body, ['Response', 'TransactionID', 'UniqueID', 'OrderID', 'Amount', 'Currency'])
        );
    }

    /**
     * Field list the bridge page auto-submits to the EPG.
     *
     * The EPG requires the payer to POST the TransactionID it issued for this
     * transaction. We keep that value server-side and inject it here rather
     * than rendering it into a link the donor could edit, and we re-validate
     * the stored portal URL against the host allowlist on every use — the URL
     * came from a remote response, so it is re-checked even though it was
     * checked at registration time.
     *
     * @param array<string,mixed> $transaction payment_transactions row
     * @return array{action:string, fields:array<string,string>}
     */
    public function bridgePayload(array $transaction): array
    {
        $portalUrl = (string) ($transaction['provider_payment_page_url'] ?? '');
        $transactionId = (string) ($transaction['provider_transaction_id'] ?? '');

        if ($transactionId === '') {
            throw new PaymentGatewayException(
                'This payment has no provider transaction id, so it cannot be resumed.',
                'etisalat',
                'missing_transaction_id'
            );
        }

        if ($portalUrl === '') {
            throw new PaymentGatewayException(
                'This payment has no stored payment page, so it cannot be resumed.',
                'etisalat',
                'missing_payment_page'
            );
        }

        $portalUrl = $this->validatePaymentPageUrl($portalUrl);

        return [
            'action' => $portalUrl,
            'fields' => ['TransactionID' => $transactionId],
        ];
    }

    /**
     * The hosted payment page must be HTTPS and on an allowlisted EPG host.
     * This is the guard against a tampered PaymentPortal value redirecting
     * donors to an attacker.
     */
    public function validatePaymentPageUrl(string $url): string
    {
        if (!Str::isHttpsUrl($url)) {
            $this->logger->security('Etisalat returned a non-HTTPS payment page URL', ['gateway' => 'etisalat']);
            throw new PaymentGatewayException(
                'Etisalat returned an invalid (non-HTTPS) payment page URL.',
                'etisalat',
                'invalid_payment_url'
            );
        }

        $host = Str::host($url) ?? '';
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
            $this->logger->security('Etisalat payment page host is not allowlisted', ['host' => $host]);
            throw new PaymentGatewayException(
                'Etisalat returned a payment page on an unrecognised host.',
                'etisalat',
                'untrusted_payment_url'
            );
        }

        return $url;
    }

    /**
     * @param array<string,mixed> $context
     */
    /**
     * Authenticate against the EPG with a deliberately unknown TransactionID.
     *
     * No payment is created or altered: a valid merchant account answers with
     * "transaction not found", while bad credentials are refused outright.
     */
    public function credentialsValid(): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $response = $this->api->send(EtisalatApiClient::OP_FINALIZATION, [
            'Finalization' => [
                'Customer'      => (string) ($this->config['customer'] ?? ''),
                'TransactionID' => '000000000000',
                'UserName'      => (string) ($this->config['username'] ?? ''),
                'Password'      => (string) ($this->config['password'] ?? ''),
            ],
        ]);

        $extracted = $this->api->extract('Finalization', $response);
        $message = mb_strtolower((string) $extracted['message']);

        foreach (['authentication', 'invalid user', 'invalid password', 'access denied', 'not authorized', 'credential'] as $needle) {
            if ($message !== '' && str_contains($message, $needle)) {
                $this->logger->security('Etisalat credential check rejected', ['reason' => $needle]);
                return false;
            }
        }

        return true;
    }

    public function verify(array $context): PaymentVerificationResult
    {
        $transactionId = (string) ($context['providerTransactionId'] ?? '');
        $expectedAmountMinor = $context['expectedAmountMinor'] ?? null;
        $expectedCurrency = $context['expectedCurrency'] ?? null;

        if ($transactionId === '') {
            throw new PaymentGatewayException(
                'Cannot finalize an Etisalat payment without a TransactionID.',
                'etisalat',
                'missing_transaction_id'
            );
        }

        $response = $this->api->send(EtisalatApiClient::OP_FINALIZATION, [
            'Finalization' => [
                'Customer'      => (string) ($this->config['customer'] ?? ''),
                'TransactionID' => $transactionId,
                'UserName'      => (string) ($this->config['username'] ?? ''),
                'Password'      => (string) ($this->config['password'] ?? ''),
            ],
        ]);

        $extracted = $this->api->extract('Finalization', $response);
        $code = $extracted['code'];
        $message = $extracted['message'];
        $body = $extracted['block'];

        $status = $this->statusMapper->mapEtisalat($code);

        $providerAmountMinor = null;
        if (isset($body['Amount']) && is_numeric($body['Amount'])) {
            try {
                $providerAmountMinor = Money::fromProviderString((string) $body['Amount']);
            } catch (\Throwable $e) {
                $providerAmountMinor = null;
            }
        }

        $providerCurrency = isset($body['Currency']) ? strtoupper((string) $body['Currency']) : null;
        $providerOrderId = isset($body['OrderID']) ? (string) $body['OrderID'] : null;
        $returnedTxn = isset($body['TransactionID']) ? (string) $body['TransactionID'] : null;

        $safe = $this->safeSubset($body, ['Response', 'TransactionID', 'OrderID', 'Amount', 'Currency', 'Description']);

        // A finalization that reports a different transaction must never be
        // applied to our record.
        if ($returnedTxn !== null && $returnedTxn !== '' && $returnedTxn !== $transactionId) {
            $this->logger->security('Etisalat finalization transaction id mismatch', [
                'expected' => $transactionId,
                'received' => $returnedTxn,
            ]);

            return new PaymentVerificationResult(
                gateway: 'etisalat',
                status: 'processing',
                verified: false,
                providerCode: $code,
                providerMessage: 'Provider returned a different TransactionID than requested.',
                providerTransactionId: $transactionId,
                safeResponse: $safe
            );
        }

        if ($expectedAmountMinor !== null && $providerAmountMinor !== null && (int) $expectedAmountMinor !== $providerAmountMinor) {
            $this->logger->security('Etisalat amount mismatch on finalization', [
                'transaction' => $transactionId,
                'expected'    => (int) $expectedAmountMinor,
                'received'    => $providerAmountMinor,
            ]);

            return new PaymentVerificationResult(
                gateway: 'etisalat',
                status: 'processing',
                verified: false,
                providerCode: $code,
                providerMessage: 'Amount reported by the provider does not match the donation.',
                providerTransactionId: $transactionId,
                safeResponse: $safe
            );
        }

        if ($expectedCurrency !== null && $providerCurrency !== null && strtoupper((string) $expectedCurrency) !== $providerCurrency) {
            $this->logger->security('Etisalat currency mismatch on finalization', [
                'transaction' => $transactionId,
                'expected'    => (string) $expectedCurrency,
                'received'    => $providerCurrency,
            ]);

            return new PaymentVerificationResult(
                gateway: 'etisalat',
                status: 'processing',
                verified: false,
                providerCode: $code,
                providerMessage: 'Currency reported by the provider does not match the donation.',
                providerTransactionId: $transactionId,
                safeResponse: $safe
            );
        }

        return new PaymentVerificationResult(
            gateway: 'etisalat',
            status: $status,
            verified: $code !== null,
            providerStatus: $code,
            providerCode: $code,
            providerMessage: $message,
            amountMinor: $providerAmountMinor,
            currency: $providerCurrency,
            providerTransactionId: $returnedTxn ?? $transactionId,
            providerOrderId: $providerOrderId,
            safeResponse: $safe
        );
    }

    /**
     * @param array<string,mixed> $context
     */
    public function refund(array $context): PaymentRefundResult
    {
        $transactionId = (string) ($context['providerTransactionId'] ?? '');
        $amountMinor = (int) ($context['amountMinor'] ?? 0);
        $currency = strtoupper((string) ($context['currency'] ?? $this->config['currency'] ?? 'PKR'));

        if ($transactionId === '' || $amountMinor <= 0) {
            return new PaymentRefundResult(
                ok: false,
                gateway: 'etisalat',
                status: 'rejected',
                providerMessage: 'A TransactionID and a positive amount are required to refund.'
            );
        }

        try {
            $response = $this->api->send(EtisalatApiClient::OP_REFUND, [
                'Refund' => [
                    'Amount'        => Money::toProviderString($amountMinor),
                    'Currency'      => $currency,
                    'TransactionID' => $transactionId,
                    'Customer'      => (string) ($this->config['customer'] ?? ''),
                    'UserName'      => (string) ($this->config['username'] ?? ''),
                    'Password'      => (string) ($this->config['password'] ?? ''),
                ],
            ]);
        } catch (PaymentGatewayException $e) {
            return new PaymentRefundResult(
                ok: false,
                gateway: 'etisalat',
                status: 'failed',
                providerMessage: $e->getMessage(),
                amountMinor: $amountMinor
            );
        }

        $extracted = $this->api->extract('Refund', $response);
        $code = $extracted['code'];
        $ok = $code === '0';

        return new PaymentRefundResult(
            ok: $ok,
            gateway: 'etisalat',
            status: $ok ? 'completed' : 'rejected',
            providerCode: $code,
            providerMessage: $extracted['message'],
            amountMinor: $amountMinor,
            safeResponse: $this->safeSubset($extracted['block'], ['Response', 'TransactionID', 'Amount', 'Description'])
        );
    }

    /** @param array<string,mixed> $response @param array<int,string> $keys @return array<string,mixed> */
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
