<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Core\Config;
use App\Core\Csrf;
use App\Core\Logger;
use App\Core\Money;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Http\Controllers\Controller;
use App\Payments\PaymentStateToken;
use App\Repositories\DonationRepository;
use App\Repositories\PaymentRepository;
use App\Services\AuthService;
use App\Services\DonationService;

/**
 * Gateway return trips.
 *
 * Both routes are untrusted entry points: the donor's browser controls them
 * completely. We resolve the transaction from our own signed state token, sit
 * on a payment lock, and ask the provider server-to-server what actually
 * happened. The browser's own claim is only recorded for audit.
 */
final class PaymentController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Csrf $csrf,
        AuthService $auth,
        private DonationService $donations,
        private DonationRepository $donationRecords,
        private PaymentRepository $payments,
        private Logger $logger
    ) {
        parent::__construct($view, $session, $csrf, $auth);
    }

    public function meezanReturn(Request $request): Response
    {
        return $this->handleReturn($request, 'meezan');
    }

    public function etisalatReturn(Request $request): Response
    {
        return $this->handleReturn($request, 'etisalat');
    }

    /**
     * Qurbani-style result page (step 2 of fundraise-response.php).
     *
     * The bank sends the donor's browser back to fundraise-response.php with
     * ?OrderID=... and a POSTed TransactionID. Settle the payment exactly like
     * the routed callbacks do (server-to-server Finalization, idempotent, under
     * a lock) and then render the result card in the site's own layout instead
     * of redirecting, so a refresh simply re-reads the stored outcome.
     */
    public function fundraiseResult(Request $request): Response
    {
        $payload = $request->isPost() ? array_merge($request->all(), $request->json()) : $request->all();
        $scalar = static fn (mixed $v): string => is_scalar($v) ? trim((string) $v) : '';

        // Which gateway is calling back? Trust our own stored row over any
        // claim in the request; default to Etisalat (the common case).
        $gateway = 'etisalat';
        $stateToken = $scalar($payload['state'] ?? $payload['token'] ?? '');
        if ($scalar($payload['TransactionID'] ?? $payload['transactionId'] ?? '') !== '') {
            $gateway = 'etisalat';
        } elseif ($stateToken !== '') {
            $stored = $this->payments->findByCallbackState(hash('sha256', $stateToken));
            if ($stored !== null && (string) ($stored['gateway_code'] ?? '') !== '') {
                $gateway = strtolower((string) $stored['gateway_code']);
            }
        } else {
            foreach (['merchant_order_id', 'orderNumber', 'OrderID', 'orderId'] as $key) {
                $value = $scalar($payload[$key] ?? '');
                if ($value === '') {
                    continue;
                }
                $stored = $this->payments->findByMerchantOrderId($value)
                    ?? $this->payments->findByProviderOrderId('etisalat', $value)
                    ?? $this->payments->findByProviderOrderId('meezan', $value);
                if ($stored !== null && (string) ($stored['gateway_code'] ?? '') !== '') {
                    $gateway = strtolower((string) $stored['gateway_code']);
                    break;
                }
            }
        }

        try {
            $redirect = $this->handleReturn($request, $gateway === 'meezan' ? 'meezan' : 'etisalat');
        } catch (\Throwable $e) {
            $this->logger->payment('Fundraise result verification failed', [
                'gateway' => $gateway,
                'error'   => $e->getMessage(),
            ]);
            $redirect = $this->redirect('/donation/failed?reason=unknown');
        }

        $location = (string) ($redirect->headers()['Location'] ?? '');
        $query = [];
        parse_str((string) (parse_url($location, PHP_URL_QUERY) ?? ''), $query);
        $reference = isset($query['ref']) && is_scalar($query['ref']) ? trim((string) $query['ref']) : '';
        $reason = isset($query['reason']) && is_scalar($query['reason']) ? trim((string) $query['reason']) : '';

        $outcome = 'failed';
        if (str_contains($location, '/donation/success')) {
            $outcome = 'completed';
        } elseif (str_contains($location, '/donation/processing')) {
            $outcome = 'processing';
        }

        $donation = $reference !== '' ? $this->donationRecords->findByReference($reference) : null;

        $retryUrl = Response::locationFor('/fundraisers');
        if ($donation !== null && !empty($donation['fundraiser_slug'])) {
            $retryUrl = Response::locationFor('/donate/' . rawurlencode((string) $donation['fundraiser_slug']));
        }

        $titles = [
            'completed'  => 'Payment successful',
            'processing' => 'Payment being confirmed',
            'failed'     => 'Payment not completed',
        ];

        $response = $this->render('public/donate/response', [
            'pageTitle'  => $titles[$outcome],
            'outcome'    => $outcome,
            'reason'     => $reason,
            'reference'  => $reference,
            'donation'   => $donation,
            'amountText' => $donation !== null
                ? Money::format((int) $donation['amount_minor'], (string) ($donation['currency'] ?? 'PKR'))
                : '',
            'retryUrl'   => $retryUrl,
            'statusUrl'  => $reference !== ''
                ? Response::locationFor('/donation/' . rawurlencode($reference))
                : Response::locationFor('/fundraisers'),
            'org'        => (array) Config::get('app.org', []),
        ]);

        return $response->withHeader('Cache-Control', 'no-store');
    }

    private function handleReturn(Request $request, string $gateway): Response
    {
        $payload = $request->isPost() ? array_merge($request->all(), $request->json()) : $request->all();

        // Signed state token, issued by us at registration time. Ignore
        // non-scalar input rather than letting malformed callbacks emit casts
        // or notices into the response.
        $stateValue = $payload['state'] ?? $payload['token'] ?? '';
        $stateToken = is_string($stateValue) || is_int($stateValue) || is_float($stateValue)
            ? trim((string) $stateValue)
            : '';

        if ($stateToken === '') {
            // Meezan and the EPG both drop unknown query parameters from the
            // return URL, so fall back to whatever identifier they did echo
            // back. This never authorises a status change on its own: it only
            // identifies the transaction whose state we then re-derive.
            $candidates = [];
            foreach (['merchant_order_id', 'orderNumber', 'OrderID', 'orderId', 'TransactionID', 'transactionId'] as $key) {
                $value = $payload[$key] ?? '';
                if (!is_string($value) && !is_int($value) && !is_float($value)) {
                    continue;
                }
                $candidate = trim((string) $value);
                if ($candidate !== '') {
                    $candidates[] = $candidate;
                }
            }

            foreach ($candidates as $candidate) {
                $transaction = $this->payments->findByMerchantOrderId($candidate)
                    ?? $this->payments->findByProviderTransactionId($gateway, $candidate)
                    ?? $this->payments->findByProviderOrderId($gateway, $candidate);

                if ($transaction !== null) {
                    $stateToken = $this->stateTokenFromTransaction($transaction);
                    break;
                }
            }
        }

        $result = $this->donations->handleCallback($gateway, $request->isPost() ? 'POST' : 'GET', $stateToken ?: null, $payload);

        $this->logger->payment('Gateway return processed', [
            'gateway' => $gateway,
            'outcome' => $result['outcome'],
            'status'  => $result['status'],
        ]);

        // DonationService returns processing outcomes such as "processed",
        // "duplicate" and "locked" separately from the authoritative database
        // status. Route by that status and always carry the public reference so
        // the donor sees the real donation rather than a missing-result page.
        $status = (string) ($result['status'] ?? '');
        $reference = trim((string) ($result['reference'] ?? ''));
        $ref = $reference !== '' ? '?ref=' . rawurlencode($reference) : '';

        return match ($status) {
            'completed' => $this->redirect('/donation/success' . $ref),
            'pending', 'processing' => $this->redirect('/donation/processing' . $ref),
            'cancelled' => $this->redirect('/donation/failed' . $ref . ($ref === '' ? '?' : '&') . 'reason=cancelled'),
            'failed', 'abandoned' => $this->redirect('/donation/failed' . $ref . ($ref === '' ? '?' : '&') . 'reason=failed'),
            default => $this->redirect('/donation/failed?reason=' . rawurlencode((string) ($result['outcome'] ?? 'unknown'))),
        };
    }

    /**
     * Etisalat hands the payer a form that must be POSTed to its payment page.
     * Resolve the compact signed state to a stored transaction server-side and
     * auto-submit its provider TransactionID; new links do not expose that ID.
     */
    public function etisalatBridge(Request $request): Response
    {
        $bridgeToken = (string) $request->routeParam('txn', '');
        $transaction = $bridgeToken !== ''
            ? $this->payments->findByCallbackState(hash('sha256', $bridgeToken))
            : null;

        // Compatibility for payment bridge links created before signed-state
        // URLs were introduced. New bridge URLs never expose TransactionID.
        if ($transaction === null && $bridgeToken !== '') {
            $transaction = $this->payments->findByProviderTransactionId('etisalat', $bridgeToken);
        }

        if ($transaction !== null
            && (strtolower((string) ($transaction['gateway_code'] ?? '')) !== 'etisalat'
                || PaymentStateToken::expiryOf($transaction) < time())) {
            $transaction = null;
        }

        if ($transaction === null) {
            // A stale or malformed payment hand-off is not a missing fundraiser.
            // Keep the donor on a payment-specific result page with next steps.
            return $this->bridgeError(
                'Payment session not found',
                'We could not find this payment session. Return to the donation form to start again. If your bank shows a debit, contact support with your bank transaction reference.',
                404
            );
        }

        $donation = $this->donationRecords->find((int) $transaction['donation_id']);
        if ($donation === null) {
            return $this->bridgeError(
                'Payment session not found',
                'We could not find this payment session. Return to the donation form to start again. If your bank shows a debit, contact support with your bank transaction reference.',
                404
            );
        }

        // Do not replay a hosted payment page for a finalized donation or a
        // transaction already marked terminal; show its real stored status.
        if (!in_array((string) $donation['status'], ['pending', 'processing'], true)
            || !in_array((string) $transaction['status'], ['pending', 'processing'], true)) {
            return $this->redirect('/donation/' . rawurlencode((string) $donation['public_reference']));
        }

        $gateway = app(\App\Payments\PaymentGatewayManager::class)->gateway('etisalat');

        try {
            /** @var array{action:string,fields:array<string,string>} $bridge */
            $bridge = $gateway->bridgePayload($transaction);
        } catch (\Throwable $e) {
            // Includes the host-allowlist rejection: if the stored portal URL
            // is not an approved EPG host we refuse to send the payer there.
            $this->logger->security('Etisalat bridge refused', [
                'reason' => $e->getMessage(),
            ]);

            return $this->bridgeError(
                'We could not resume this payment',
                'This payment session could not be resumed safely. Return to the donation form to start again. If your bank shows a debit, contact support with your bank transaction reference.',
                409
            );
        }

        if (empty($bridge['action']) || empty($bridge['fields'])) {
            return $this->bridgeError(
                'We could not resume this payment',
                'This payment session could not be resumed safely. Return to the donation form to start again. If your bank shows a debit, contact support with your bank transaction reference.',
                409
            );
        }

        // Intermediate page: auto-POSTs to the provider and shows a manual
        // fallback button for browsers with JavaScript disabled.
        return $this->render('public/donate/bridge', [
            'pageTitle'  => 'Continuing to secure payment…',
            'action'     => (string) $bridge['action'],
            'fields'     => (array) $bridge['fields'],
            'gatewayLabel' => 'UBL / Etisalat payment gateway',
        ], 'layouts/blank');
    }

    /**
     * Re-derives the signed state value for a transaction.
     *
     * The plaintext state is never stored — only sha256(state) — but it is a
     * keyed HMAC over the merchant order id and expiry, so we can rebuild it
     * from the row itself. A return trip that arrives without our state
     * parameter therefore still resolves, while a forged one cannot.
     *
     * @param array<string,mixed> $transaction
     */
    private function stateTokenFromTransaction(array $transaction): string
    {
        try {
            return PaymentStateToken::fromTransaction($transaction);
        } catch (\Throwable $e) {
            $this->logger->security('Unable to re-derive payment state', ['reason' => $e->getMessage()]);

            return '';
        }
    }

    private function bridgeError(string $heading, string $message, int $status): Response
    {
        return $this->render('public/donate/status', [
            'pageTitle'    => $heading,
            'donation'     => null,
            'transactions' => [],
            'callbacks'    => [],
            'expected'     => null,
            'emptyHeading' => $heading,
            'donorMessage' => $message,
        ], 'layouts/public', $status);
    }
}
