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

    public function returnUrl(string $callbackState, ?string $merchantOrderId = null): string
    {
        // Same contract as the working Qurbani site: the EPG ReturnPath is the
        // standalone response page with our OrderID in the query string, and
        // the bank then POSTs its TransactionID to it. The response page finds
        // the stored transaction by OrderID / TransactionID, so it does not
        // depend on a session, a signed token or the donor's browser state.
        // The EPG guide limits ReturnPath to 256 characters.
        $url = rtrim($this->baseUrl, '/') . '/fundraise-response.php';
        if ($merchantOrderId !== null && preg_match('/^[A-Za-z0-9]{1,16}$/D', $merchantOrderId)) {
            $url .= '?OrderID=' . $merchantOrderId;
        }
        $withState = $url . (str_contains($url, '?') ? '&' : '?') . 'state=' . rawurlencode($callbackState);
        if ($callbackState !== '' && strlen($withState) <= 256) {
            $url = $withState;
        }
        if (strlen($url) > 256) {
            throw new PaymentGatewayException(
                'The Etisalat return URL exceeds the gateway 256-character limit. Shorten the configured site URL.',
                'etisalat',
                'return_url_too_long'
            );
        }

        return $url;
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
        if (!preg_match('/^[A-Za-z0-9]{1,16}$/D', $request->merchantOrderId)) {
            throw new PaymentGatewayException(
                'Etisalat OrderID must contain 1 to 16 alphanumeric characters.',
                'etisalat',
                'invalid_order_id'
            );
        }

        // Qurbani's working EPG implementation uses a 50-character ASCII
        // OrderInfo. Keep this merchant detail conservative too.
        $orderInfo = preg_replace('/[^A-Za-z0-9 ]/', '', $request->description) ?? '';
        $orderInfo = rtrim(substr(trim(preg_replace('/ +/', ' ', $orderInfo) ?? ''), 0, 50));
        if ($orderInfo === '') {
            $orderInfo = 'Donation';
        }

        $block = [
            'Customer'        => (string) ($this->config['customer'] ?? ''),
            'Channel'         => 'Web',
            'Amount'          => $request->amountDecimal(),
            'Currency'        => $currency,
            'OrderID'         => $request->merchantOrderId,
            // The EPG payment page shows OrderName, so use the order number
            // (0001, 0002 …) rather than a fixed word, to make payments traceable.
            'OrderName'       => $request->merchantOrderId,
            'OrderInfo'       => $orderInfo,
            'TransactionHint' => (string) ($this->config['transaction_hint'] ?? 'CPT:Y;VCC:Y;'),
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
        if (mb_strlen($transactionId, 'UTF-8') > 128) {
            throw new PaymentGatewayException(
                'Etisalat returned a TransactionID that exceeds the supported length.',
                'etisalat',
                'invalid_transaction_id'
            );
        }

        $paymentPage = (string) ($body['PaymentPage'] ?? $body['PaymentPortal'] ?? '');
        $this->validatePaymentPageUrl($paymentPage);

        $uniqueId = isset($body['UniqueID']) ? (string) $body['UniqueID'] : null;
        $bridgeState = $request->metadata['callback_state'] ?? null;
        if (!is_string($bridgeState) || !preg_match('/^[A-Za-z0-9_-]{16,128}$/D', $bridgeState)) {
            throw new PaymentGatewayException(
                'Etisalat payment bridge state is missing or invalid.',
                'etisalat',
                'missing_bridge_state'
            );
        }

        // The donor gets a compact signed state URL, not the bank TransactionID.
        // The bridge resolves that state to our stored row and inserts the
        // provider-issued TransactionID into the hosted POST server-side.
        $bridgeUrl = rtrim($this->baseUrl, '/') . '/payments/etisalat/bridge/' . rawurlencode($bridgeState);

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
            safeResponse: $this->safeSubset($body, ['ResponseCode', 'ResponseDescription', 'TransactionID', 'UniqueID', 'OrderID', 'Amount', 'Currency'])
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
        if (strlen($url) > 2048) {
            $this->logger->security('Etisalat payment page URL exceeds the supported length', ['gateway' => 'etisalat']);
            throw new PaymentGatewayException(
                'Etisalat returned a payment page URL that is too long to store safely.',
                'etisalat',
                'invalid_payment_url'
            );
        }

        $parts = parse_url($url);
        if (!Str::isHttpsUrl($url)
            || !is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])) {
            $this->logger->security('Etisalat returned an invalid payment page URL', ['gateway' => 'etisalat']);
            throw new PaymentGatewayException(
                'Etisalat returned an invalid (non-HTTPS or credential-bearing) payment page URL.',
                'etisalat',
                'invalid_payment_url'
            );
        }

        $host = strtolower((string) $parts['host']);
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
        $expectedOrderId = (string) ($context['merchantOrderId'] ?? $context['providerOrderId'] ?? '');
        $expectedAmountMinor = isset($context['expectedAmountMinor'])
            ? (int) $context['expectedAmountMinor']
            : null;
        $expectedCurrency = isset($context['expectedCurrency'])
            ? strtoupper((string) $context['expectedCurrency'])
            : null;

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

        $extracted = $this->api->extract(EtisalatApiClient::OP_FINALIZATION, $response);
        $code = $extracted['code'];
        $message = $extracted['message'];
        // EPG field casing varies by merchant API version.
        $body = array_change_key_case($extracted['block'], CASE_LOWER);
        $status = $this->statusMapper->mapEtisalat($code);

        $providerOrderId = isset($body['orderid']) && is_scalar($body['orderid'])
            ? (string) $body['orderid']
            : null;
        $returnedTxn = isset($body['transactionid']) && is_scalar($body['transactionid'])
            ? (string) $body['transactionid']
            : null;
        $providerCurrency = isset($body['currency']) && is_scalar($body['currency'])
            ? strtoupper((string) $body['currency'])
            : null;

        $providerAmount = $body['amount'] ?? null;
        if (is_array($providerAmount)) {
            $providerAmount = $providerAmount['Value'] ?? $providerAmount['value'] ?? null;
        }
        $providerAmountDecimal = is_scalar($providerAmount)
            ? self::canonicalDecimal((string) $providerAmount)
            : null;

        $safe = $this->safeSubset($body, [
            'responsecode', 'responsedescription', 'transactionid', 'orderid',
            'amount', 'currency', 'description', 'approvalcode',
        ]);

        foreach (['transactionid', 'orderid', 'currency'] as $field) {
            if (array_key_exists($field, $body) && (!is_scalar($body[$field]) || trim((string) $body[$field]) === '')) {
                return new PaymentVerificationResult(
                    gateway: 'etisalat', status: 'processing', verified: false,
                    providerCode: $code, providerMessage: 'Etisalat returned an invalid ' . $field . '.',
                    providerTransactionId: $transactionId, safeResponse: $safe
                );
            }
        }
        if (array_key_exists('amount', $body) && $providerAmountDecimal === null) {
            return new PaymentVerificationResult(
                gateway: 'etisalat', status: 'processing', verified: false,
                providerCode: $code, providerMessage: 'Etisalat returned an invalid amount.',
                providerTransactionId: $transactionId, safeResponse: $safe
            );
        }

        // Finalization must refer to the same registered TransactionID. The
        // posted callback ID was already matched against our saved ID before
        // this request; if EPG echoes an ID, it must also match.
        if ($returnedTxn !== null && $returnedTxn !== '' && !hash_equals($transactionId, $returnedTxn)) {
            $this->logger->security('Etisalat finalization transaction id mismatch', [
                'expected' => $transactionId,
                'received' => $returnedTxn,
            ]);

            return new PaymentVerificationResult(
                gateway: 'etisalat',
                status: 'processing',
                verified: false,
                providerCode: $code,
                providerMessage: 'Etisalat returned a different TransactionID than the registered payment.',
                providerTransactionId: $transactionId,
                safeResponse: $safe
            );
        }

        if ($code === null) {
            return new PaymentVerificationResult(
                gateway: 'etisalat',
                status: 'processing',
                verified: false,
                providerMessage: $message ?? 'Etisalat Finalization did not return a numeric ResponseCode.',
                providerTransactionId: $transactionId,
                safeResponse: $safe
            );
        }

        // The supplied bank plugin and Qurbani integration require an
        // ApprovalCode on a successful Finalization. A code 0 without it is
        // indeterminate, not proof that this payment may be completed.
        $approvalCode = $body['approvalcode'] ?? null;
        if ($status === 'completed' && (!is_scalar($approvalCode) || trim((string) $approvalCode) === '')) {
            $this->logger->security('Etisalat success response omitted ApprovalCode', [
                'transaction' => $transactionId,
            ]);

            return new PaymentVerificationResult(
                gateway: 'etisalat',
                status: 'processing',
                verified: false,
                providerCode: $code,
                providerMessage: 'Etisalat Finalization did not include an ApprovalCode.',
                providerTransactionId: $transactionId,
                providerOrderId: $providerOrderId,
                safeResponse: $safe
            );
        }

        // Finalization is authenticated and uses ONLY the TransactionID saved at
        // Registration. Some EPG versions omit OrderID/Amount; a matching bank
        // TransactionID plus approval binds that response to the registered sum.
        // Never ignore a conflicting echo. Without a returned TransactionID,
        // require BOTH OrderID and Amount to bind the successful response.
        if ($status === 'completed') {
            if ($expectedOrderId === ''
                || ($providerOrderId !== null && !self::sameOrderNumber($expectedOrderId, $providerOrderId))
                || (($returnedTxn === null || $returnedTxn === '') && $providerOrderId === null)) {
                $this->logger->security('Etisalat order id mismatch on finalization', [
                    'transaction' => $transactionId,
                ]);

                return new PaymentVerificationResult(
                    gateway: 'etisalat',
                    status: 'processing',
                    verified: false,
                    providerCode: $code,
                    providerMessage: 'Etisalat Finalization OrderID did not match the registered donation.',
                    providerTransactionId: $transactionId,
                    providerOrderId: $providerOrderId,
                    safeResponse: $safe
                );
            }

            $expectedAmountDecimal = $expectedAmountMinor !== null && $expectedAmountMinor > 0
                ? self::canonicalDecimal(Money::toProviderString($expectedAmountMinor))
                : null;
            if ($expectedAmountDecimal === null
                || ($providerAmount !== null && ($providerAmountDecimal === null
                    || !hash_equals($expectedAmountDecimal, $providerAmountDecimal)))
                || (($returnedTxn === null || $returnedTxn === '') && $providerAmountDecimal === null)) {
                $this->logger->security('Etisalat amount mismatch on finalization', [
                    'transaction' => $transactionId,
                ]);

                return new PaymentVerificationResult(
                    gateway: 'etisalat',
                    status: 'processing',
                    verified: false,
                    providerCode: $code,
                    providerMessage: 'Etisalat Finalization amount did not match the registered donation.',
                    providerTransactionId: $transactionId,
                    providerOrderId: $providerOrderId,
                    safeResponse: $safe
                );
            }

            // Some EPG Finalization responses do not contain Currency. The
            // TransactionID is bound to the currency submitted at Registration;
            // when a currency is echoed, still reject any mismatch.
            if ($expectedCurrency !== null
                && $providerCurrency !== null
                && $expectedCurrency !== $providerCurrency) {
                $this->logger->security('Etisalat currency mismatch on finalization', [
                    'transaction' => $transactionId,
                ]);

                return new PaymentVerificationResult(
                    gateway: 'etisalat',
                    status: 'processing',
                    verified: false,
                    providerCode: $code,
                    providerMessage: 'Etisalat Finalization currency did not match the registered donation.',
                    providerTransactionId: $transactionId,
                    providerOrderId: $providerOrderId,
                    safeResponse: $safe
                );
            }
        }

        $providerAmountMinor = null;
        if ($providerAmountDecimal !== null && $expectedAmountMinor !== null
            && $providerAmountDecimal === self::canonicalDecimal(Money::toProviderString($expectedAmountMinor))) {
            // This also supports EPG's documented/observed three-decimal
            // representation (e.g. 50.000) without floating-point conversion.
            $providerAmountMinor = $expectedAmountMinor;
        } elseif (is_scalar($providerAmount)) {
            try {
                $providerAmountMinor = Money::fromProviderString((string) $providerAmount);
            } catch (\Throwable) {
                $providerAmountMinor = null;
            }
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
     * Canonicalize a non-negative provider decimal without float conversion.
     * Trailing fractional zeroes are ignored, so 50, 50.00 and 50.000 compare
     * as the same amount.
     */
    private static function canonicalDecimal(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || !preg_match('/^\d+(?:\.\d+)?$/', $value)) {
            return null;
        }

        $parts = explode('.', $value, 2);
        $whole = ltrim($parts[0], '0');
        $fraction = isset($parts[1]) ? rtrim($parts[1], '0') : '';
        $whole = $whole === '' ? '0' : $whole;

        return $whole . ($fraction !== '' ? '.' . $fraction : '');
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
            safeResponse: $this->safeSubset($extracted['block'], ['ResponseCode', 'ResponseDescription', 'TransactionID', 'Amount', 'Description'])
        );
    }

    /** @param array<string,mixed> $response @param array<int,string> $keys @return array<string,mixed> */
    /**
     * Order numbers are compared exactly, except that a bank echoing "0042" as
     * "42" (leading zeros dropped) is still the same number.
     */
    private static function sameOrderNumber(string $expected, string $actual): bool
    {
        if (hash_equals($expected, $actual)) {
            return true;
        }
        return ctype_digit($expected) && ctype_digit($actual)
            && hash_equals(ltrim($expected, '0'), ltrim($actual, '0'));
    }

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
