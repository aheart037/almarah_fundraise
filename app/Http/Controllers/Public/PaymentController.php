<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Core\Csrf;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Http\Controllers\Controller;
use App\Payments\PaymentStateToken;
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

    private function handleReturn(Request $request, string $gateway): Response
    {
        $payload = $request->isPost() ? array_merge($request->all(), $request->json()) : $request->all();

        // Signed state token, issued by us at registration time.
        $stateToken = (string) ($payload['state'] ?? $payload['token'] ?? '');

        if ($stateToken === '') {
            // Meezan and the EPG both drop unknown query parameters from the
            // return URL, so fall back to whatever identifier they did echo
            // back. This never authorises a status change on its own: it only
            // identifies the transaction whose state we then re-derive.
            $candidates = [
                (string) ($payload['merchant_order_id'] ?? ''),
                (string) ($payload['orderNumber'] ?? ''),
                (string) ($payload['OrderID'] ?? ''),
                (string) ($payload['orderId'] ?? ''),
                (string) ($payload['TransactionID'] ?? ''),
                (string) ($payload['transactionId'] ?? ''),
            ];

            foreach (array_filter($candidates, static fn (string $v): bool => $v !== '') as $candidate) {
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
        ]);

        return match ($result['outcome']) {
            'completed'  => $this->redirect('/donation/success?ref=' . rawurlencode((string) $result['reference'])),
            'processing' => $this->redirect('/donation/processing?ref=' . rawurlencode((string) $result['reference'])),
            'cancelled'  => $this->redirect('/donation/failed?ref=' . rawurlencode((string) $result['reference']) . '&reason=cancelled'),
            'failed'     => $this->redirect('/donation/failed?ref=' . rawurlencode((string) $result['reference']) . '&reason=failed'),
            'unknown'    => $this->redirect('/donation/failed?reason=unknown'),
            default      => $this->redirect('/donation/failed?reason=' . rawurlencode($result['outcome'])),
        };
    }

    /**
     * Etisalat hands the payer a form that must be POSTed to its payment page.
     * We resolve the stored TransactionID server-side and auto-submit it, so
     * the transaction id is never rendered into a link the donor can edit.
     */
    public function etisalatBridge(Request $request): Response
    {
        $txn = (string) $request->routeParam('txn', '');
        $transaction = $this->payments->findByProviderTransactionId('etisalat', $txn);

        if ($transaction === null) {
            return $this->render('public/errors/404', ['pageTitle' => 'Payment session not found'], 'layouts/public', 404);
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

            $this->payments->updateTransaction((int) $transaction['id'], [
                'status' => 'failed',
                'provider_response_description' => mb_substr($e->getMessage(), 0, 255),
            ]);

            $this->flashError('We could not resume that payment. Please start again.');
            return $this->redirect('/donation/failed?reason=session_expired');
        }

        if (empty($bridge['action']) || empty($bridge['fields'])) {
            $this->flashError('We could not resume that payment. Please start again.');
            return $this->redirect('/donation/failed?reason=session_expired');
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
}
