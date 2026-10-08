<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Core\RateLimiter;
use App\Core\Str;
use App\Payments\Dtos\PaymentRefundResult;
use App\Payments\Dtos\PaymentRequest;
use App\Payments\Exceptions\PaymentGatewayException;
use App\Payments\PaymentGatewayManager;
use App\Payments\PaymentStateToken;
use App\Payments\PaymentLock;
use App\Repositories\DonationRepository;
use App\Repositories\DonorRepository;
use App\Repositories\PaymentRepository;
use InvalidArgumentException;

/**
 * Orchestrates the donation lifecycle. Contains no provider-specific logic:
 * every gateway interaction goes through PaymentGatewayManager.
 */
final class DonationService
{
    public function __construct(
        private Database $db,
        private DonationRepository $donations,
        private PaymentRepository $payments,
        private DonorRepository $donors,
        private PaymentGatewayManager $gateways,
        private PaymentStatusService $statusService,
        private PaymentLock $lock,
        private RateLimiter $rateLimiter,
        private AuditService $audit,
        private Logger $logger
    ) {
    }

    // ------------------------------------------------------------- initiation

    /**
     * Create a pending donation and register it with the chosen gateway.
     *
     * @param array<string,mixed> $input   validated donor input
     * @param array<string,mixed> $target  fundraiser / campaign / team the gift belongs to
     * @return array{donation_id:int, reference:string, redirect_url:string, gateway:string}
     */
    public function initiate(array $input, array $target, string $gatewayCode): array
    {
        $gatewayCode = strtolower(trim($gatewayCode));

        $gateway = $this->gateways->gateway($gatewayCode);

        if (!$gateway->isEnabled()) {
            throw new PaymentGatewayException('That payment method is currently unavailable.', $gatewayCode, 'disabled');
        }
        if (!$gateway->isConfigured()) {
            throw new PaymentGatewayException('That payment method is not fully configured yet.', $gatewayCode, 'not_configured');
        }

        // Providers are given an absolute return URL and then send the donor
        // back to it. If the site address is not configured we could only guess
        // it from the Host header, and a spoofed Host would let a stranger's
        // domain collect the return trip (and the signed state that comes with
        // it). Refuse the payment and tell the administrator what to set.
        if (!preg_match('#^https?://[^\s/]+#i', $gateway->returnUrl('config-check'))) {
            throw new PaymentGatewayException(
                'Online payments are not switched on yet: set site_url in config.php (or APP_URL) to the full address of this site, including https://.',
                $gatewayCode,
                'site_url_missing'
            );
        }

        $currency = (string) Config::get('app.currency', 'PKR');
        $amountMinor = (int) $input['amount_minor'];

        $min = (int) Config::get('app.donations.min_minor', 10000);
        $max = (int) Config::get('app.donations.max_minor', 500000000);

        if ($amountMinor < $min) {
            throw new InvalidArgumentException('Donations must be at least ' . money($min, $currency) . '.');
        }
        if ($amountMinor > $max) {
            throw new InvalidArgumentException('Donations may not exceed ' . money($max, $currency) . '.');
        }

        // Throttle donation creation per IP + email to blunt card-testing and
        // duplicate-submission abuse.
        $throttleKey = 'donate:' . ($input['ip'] ?? '0.0.0.0') . ':' . mb_strtolower((string) $input['email']);
        $maxAttempts = (int) Config::get('security.donation_throttle.max_attempts', 12);
        $decay = (int) Config::get('security.donation_throttle.decay_minutes', 10);

        if ($this->rateLimiter->tooManyAttempts($throttleKey, $maxAttempts)) {
            throw new InvalidArgumentException('Too many donation attempts. Please wait a few minutes and try again.');
        }
        $this->rateLimiter->hit($throttleKey, $decay);

        $gatewayId = $this->gateways->gatewayId($gatewayCode);

        // The donation row and its payment transaction are written together:
        // a donation must never exist without the transaction that will settle
        // it, and a transaction must never reference a donation that failed to
        // save.
        return $this->db->transaction(function () use ($input, $target, $gateway, $gatewayCode, $gatewayId, $amountMinor, $currency): array {
            $donorId = $this->donors->findOrCreate(
                (string) $input['name'],
                (string) $input['email'],
                $input['phone'] ?? null,
                $input['user_id'] ?? null
            );

            $reference = Str::publicReference('ALM');

            $donationId = $this->donations->create([
                'public_reference' => $reference,
                'donor_id'         => $donorId,
                'fundraiser_id'    => $target['fundraiser_id'] ?? null,
                'campaign_id'      => $target['campaign_id'] ?? null,
                'team_id'          => $target['team_id'] ?? null,
                'gateway_id'       => $gatewayId,
                'amount_minor'     => $amountMinor,
                'currency'         => $currency,
                'donor_message'    => $input['message'] ?? null,
                'anonymous'        => !empty($input['anonymous']),
                'status'           => 'pending',
            ]);

            $merchantOrderId = $this->buildMerchantOrderId($reference);
            $stateTtl = (int) Config::get('payments.callback.state_ttl_minutes', 180);
            $stateExpiresAt = time() + ($stateTtl * 60);

            // Signed, re-derivable state: the provider only ever sees this
            // value, and we can rebuild it from the stored row if the return
            // trip comes back with just our order number. Only its hash is
            // persisted, and the expiry window below is what actually bounds
            // its life.
            $callbackState = PaymentStateToken::issue($merchantOrderId);

            $transactionId = $this->payments->createTransaction([
                'donation_id'               => $donationId,
                'gateway_id'                => $gatewayId,
                'environment'               => $gateway->environment(),
                'merchant_order_id'         => $merchantOrderId,
                'callback_state_hash'       => hash('sha256', $callbackState),
                'callback_state_expires_at' => gmdate('Y-m-d H:i:s', $stateExpiresAt),
                'amount_minor'              => $amountMinor,
                'currency'                  => $currency,
                'status'                    => 'pending',
                'request_reference'         => Str::randomHex(8),
                'idempotency_key'           => 'reg:' . $gatewayCode . ':' . $donationId . ':' . Str::randomHex(8),
            ]);

            $request = new PaymentRequest(
                merchantOrderId: $merchantOrderId,
                amountMinor: $amountMinor,
                currency: $currency,
                description: $this->describeDonation($target),
                returnUrl: $gateway->returnUrl($callbackState),
                callbackUrl: $gateway->returnUrl($callbackState),
                idempotencyKey: 'reg:' . $gatewayCode . ':' . $donationId,
                customerName: (string) $input['name'],
                customerEmail: (string) $input['email'],
                customerIp: $input['ip'] ?? null,
                metadata: ['donation_reference' => $reference]
            );

            try {
                $registration = $gateway->register($request);
            } catch (PaymentGatewayException $e) {
                $this->donations->updateStatus($donationId, 'failed');
                $this->payments->updateTransaction($transactionId, [
                    'status'                        => 'failed',
                    'provider_response_code'        => $e->providerCode() !== null ? (string) $e->providerCode() : $e->reason(),
                    'provider_response_description'=> mb_substr($e->getMessage(), 0, 255),
                ]);

                $this->audit->log('donation.registration_failed', 'donation', (string) $donationId, [
                    'gateway' => $gatewayCode,
                    'reason'  => $e->reason(),
                ]);

                $this->logger->payment('Gateway registration failed', [
                    'gateway'   => $gatewayCode,
                    'reference' => $reference,
                    'reason'    => $e->reason(),
                ]);

                // Keep the gateway's own reason code (untrusted_payment_url,
                // currency_mismatch, transport_error, ...) so operators can
                // tell a phishing attempt from an outage; only the donor-facing
                // message is generic.
                throw new PaymentGatewayException(
                    'We could not start the payment with ' . $gateway->label() . '. Please try again or choose another payment method.',
                    $gatewayCode,
                    $e->reason() !== '' ? $e->reason() : 'registration_failed',
                    $e->providerCode(),
                    $e->safeContext(),
                    $e
                );
            }

            if (!$registration->ok) {
                $this->donations->updateStatus($donationId, 'failed');
                $this->payments->updateTransaction($transactionId, [
                    'status'                        => 'failed',
                    'provider_response_code'        => $registration->providerCode,
                    'provider_response_description'=> $this->truncate($registration->providerMessage),
                ]);

                $this->logger->payment('Gateway rejected registration', [
                    'gateway'   => $gatewayCode,
                    'reference' => $reference,
                    'code'      => $registration->providerCode,
                ]);

                throw new PaymentGatewayException(
                    $registration->providerMessage ?? 'The payment provider rejected this transaction.',
                    $gatewayCode,
                    'registration_rejected'
                );
            }

            if (!$registration->hasRedirect()) {
                // A registration without a payment page is not usable; never
                // pretend the payment succeeded.
                $this->donations->updateStatus($donationId, 'failed');
                $this->payments->updateTransaction($transactionId, [
                    'status' => 'failed',
                    'provider_response_description' => 'Gateway returned no payment page.',
                ]);

                throw new PaymentGatewayException(
                    'The payment provider did not return a payment page. Please try again.',
                    $gatewayCode,
                    'no_redirect'
                );
            }

            $this->payments->updateTransaction($transactionId, [
                'provider_transaction_id'   => $registration->providerTransactionId,
                'provider_order_id'         => $registration->providerOrderId,
                'provider_unique_id'        => $registration->providerUniqueId,
                'provider_payment_page_url' => $registration->providerPaymentPageUrl !== null
                    ? mb_substr($registration->providerPaymentPageUrl, 0, 255)
                    : null,
                'provider_response_code'    => $registration->providerCode,
                'provider_response_description' => $this->truncate($registration->providerMessage),
                'status'                    => 'processing',
                'registered_at'             => gmdate('Y-m-d H:i:s'),
                'registration_payload_hash' => hash('sha256', json_encode($registration->safeResponse ?: []) ?: ''),
            ]);

            $this->donations->updateStatus($donationId, 'processing');

            $this->audit->log('donation.registered', 'donation', (string) $donationId, [
                'gateway'   => $gatewayCode,
                'reference' => $reference,
                'amount'    => $amountMinor,
                'currency'  => $currency,
            ]);

            return [
                'donation_id'  => $donationId,
                'reference'    => $reference,
                'redirect_url' => (string) $registration->redirectUrl,
                'gateway'      => $gatewayCode,
            ];
        });
    }

    // ------------------------------------------------------------- callbacks

    /**
     * Process a gateway callback (return trip from the hosted payment page).
     *
     * The browser-supplied payload is recorded for audit but never trusted:
     * status always comes from a server-to-server verification.
     *
     * @param array<string,mixed> $payload
     * @return array{outcome:string, donation_id:?int, reference:?string, status:?string, message:string}
     */
    public function handleCallback(string $gatewayCode, string $method, ?string $stateToken, array $payload): array
    {
        $gatewayCode = strtolower(trim($gatewayCode));
        $gateway = $this->gateways->gateway($gatewayCode);
        $gatewayId = $this->gateways->gatewayId($gatewayCode);

        $safePayload = $this->sanitiseCallbackPayload($payload);

        if ($stateToken === null || $stateToken === '') {
            $this->payments->recordCallback([
                'gateway_id'        => $gatewayId,
                'callback_method'   => $method === 'POST' ? 'POST' : 'GET',
                'safe_payload'      => $safePayload,
                'validation_result' => 'rejected',
                'reason'            => 'Missing callback state.',
            ]);

            return $this->outcome('invalid', null, null, null, 'This payment link is not valid.');
        }

        $transaction = $this->payments->findByCallbackState(hash('sha256', $stateToken));

        if ($transaction === null) {
            $this->payments->recordCallback([
                'gateway_id'        => $gatewayId,
                'callback_method'   => $method === 'POST' ? 'POST' : 'GET',
                'safe_payload'      => $safePayload,
                'validation_result' => 'rejected',
                'reason'            => 'Unknown callback state.',
            ]);

            $this->logger->security('Callback with unknown state', ['gateway' => $gatewayCode]);
            return $this->outcome('invalid', null, null, null, 'We could not match this payment to a donation.');
        }

        $donation = $this->donations->find((int) $transaction['donation_id']);

        if ($donation === null) {
            $this->payments->recordCallback([
                'payment_transaction_id' => (int) $transaction['id'],
                'gateway_id'             => $gatewayId,
                'callback_method'        => $method === 'POST' ? 'POST' : 'GET',
                'safe_payload'           => $safePayload,
                'validation_result'      => 'error',
                'reason'                 => 'Donation record missing.',
            ]);

            return $this->outcome('invalid', null, null, null, 'We could not find that donation.');
        }

        // Expired callback states are rejected outright.
        if (strtotime((string) $transaction['callback_state_expires_at'] . ' UTC') < time()) {
            $this->payments->recordCallback([
                'payment_transaction_id' => (int) $transaction['id'],
                'gateway_id'             => $gatewayId,
                'callback_method'        => $method === 'POST' ? 'POST' : 'GET',
                'safe_payload'           => $safePayload,
                'validation_result'      => 'rejected',
                'reason'                 => 'Callback state expired.',
            ]);

            return $this->outcome('expired', (int) $donation['id'], (string) $donation['public_reference'], (string) $donation['status'], 'This payment session has expired.');
        }

        // Gateway-specific payload checks (e.g. Etisalat TransactionID match).
        $mismatch = $this->payloadMismatch($gatewayCode, $transaction, $payload);
        if ($mismatch !== null) {
            $this->payments->recordCallback([
                'payment_transaction_id'  => (int) $transaction['id'],
                'gateway_id'              => $gatewayId,
                'callback_method'         => $method === 'POST' ? 'POST' : 'GET',
                'safe_payload'            => $safePayload,
                'received_transaction_id' => $mismatch['received'],
                'validation_result'       => 'rejected',
                'reason'                  => $mismatch['reason'],
            ]);

            $this->logger->security('Callback payload did not match stored transaction', [
                'gateway' => $gatewayCode,
                'reason'  => $mismatch['reason'],
            ]);

            return $this->outcome('rejected', (int) $donation['id'], (string) $donation['public_reference'], (string) $donation['status'], 'That payment reference did not match.');
        }

        // Re-check state *after* taking the lock: a concurrent callback may
        // have completed the donation while we were validating.
        $lockKey = 'payment:transaction:' . (int) $transaction['id'];
        $lockTimeout = (int) Config::get('payments.callback.lock_timeout_seconds', 45);

        $result = $this->lock->withLock($lockKey, function () use ($gateway, $transaction, $donation, $method, $safePayload, $gatewayId, $gatewayCode) {
            $fresh = $this->donations->find((int) $donation['id']);

            if ($fresh !== null && in_array((string) $fresh['status'], ['completed', 'refunded'], true)) {
                $this->payments->recordCallback([
                    'payment_transaction_id' => (int) $transaction['id'],
                    'gateway_id'             => $gatewayId,
                    'callback_method'        => $method === 'POST' ? 'POST' : 'GET',
                    'safe_payload'           => $safePayload,
                    'validation_result'      => 'duplicate',
                    'reason'                 => 'Donation already finalized.',
                ]);

                return [
                    'duplicate' => true,
                    'donation'  => $fresh,
                    'status'    => (string) $fresh['status'],
                    'verified'  => null,
                ];
            }

            $verification = $gateway->verify([
                'providerOrderId'       => $transaction['provider_order_id'],
                'providerTransactionId' => $transaction['provider_transaction_id'],
                'merchantOrderId'       => $transaction['merchant_order_id'],
                'expectedAmountMinor'   => (int) $transaction['amount_minor'],
                'expectedCurrency'      => (string) $transaction['currency'],
            ]);

            $this->payments->updateTransaction((int) $transaction['id'], [
                'callback_received_at' => gmdate('Y-m-d H:i:s'),
            ]);

            $applied = $this->statusService->applyVerifiedResult($transaction, $fresh ?? $donation, $verification);

            $this->payments->recordCallback([
                'payment_transaction_id' => (int) $transaction['id'],
                'gateway_id'             => $gatewayId,
                'callback_method'        => $method === 'POST' ? 'POST' : 'GET',
                'safe_payload'           => $safePayload,
                'validation_result'      => $verification->verified ? 'accepted' : 'ignored',
                'reason'                 => $applied['reason'],
                'processed_at'           => gmdate('Y-m-d H:i:s'),
            ]);

            return [
                'duplicate' => false,
                'donation'  => $this->donations->find((int) $donation['id']),
                'status'    => $applied['status'],
                'verified'  => $verification,
            ];
        }, $lockTimeout);

        if ($result === null) {
            // Someone else is finalizing this transaction right now.
            $this->payments->recordCallback([
                'payment_transaction_id' => (int) $transaction['id'],
                'gateway_id'             => $gatewayId,
                'callback_method'        => $method === 'POST' ? 'POST' : 'GET',
                'safe_payload'           => $safePayload,
                'validation_result'      => 'duplicate',
                'reason'                 => 'Concurrent callback; lock not acquired.',
            ]);

            return $this->outcome(
                'locked',
                (int) $donation['id'],
                (string) $donation['public_reference'],
                'processing',
                'We are already confirming this payment. Please wait a moment.'
            );
        }

        $donation = $result['donation'] ?? $donation;
        $status = (string) ($result['status'] ?? $donation['status']);

        if ($result['duplicate'] === true) {
            return $this->outcome('duplicate', (int) $donation['id'], (string) $donation['public_reference'], $status, 'This donation has already been confirmed.');
        }

        return $this->outcome('processed', (int) $donation['id'], (string) $donation['public_reference'], $status, 'Thank you.');
    }

    /**
     * @param array<string,mixed> $transaction
     * @param array<string,mixed> $payload
     * @return array{received:?string, reason:string}|null
     */
    private function payloadMismatch(string $gatewayCode, array $transaction, array $payload): ?array
    {
        if ($gatewayCode !== 'etisalat') {
            return null; // Meezan's return trip carries only our own order id.
        }

        $received = $payload['TransactionID'] ?? $payload['transactionId'] ?? $payload['transaction_id'] ?? null;
        if ($received === null || $received === '') {
            return ['received' => null, 'reason' => 'Callback did not contain a TransactionID.'];
        }

        $stored = (string) ($transaction['provider_transaction_id'] ?? '');
        if ($stored === '' || (string) $received !== $stored) {
            return ['received' => (string) $received, 'reason' => 'TransactionID does not match the registered payment.'];
        }

        return null;
    }

    // ----------------------------------------------------------- reconciling

    /**
     * Re-check a pending/processing transaction against the provider.
     * Used by the admin "reconcile" action and the scheduled worker.
     *
     * @return array{status:string, changed:bool, message:string}
     */
    public function reconcile(int $transactionId, ?int $actorUserId = null): array
    {
        $transaction = $this->payments->find($transactionId);
        if ($transaction === null) {
            return ['status' => 'unknown', 'changed' => false, 'message' => 'Transaction not found.'];
        }

        $donation = $this->donations->find((int) $transaction['donation_id']);
        if ($donation === null) {
            return ['status' => 'unknown', 'changed' => false, 'message' => 'Donation not found.'];
        }

        if (in_array((string) $donation['status'], ['completed', 'refunded'], true)) {
            return ['status' => (string) $donation['status'], 'changed' => false, 'message' => 'Already finalized.'];
        }

        $gateway = $this->gateways->gateway((string) $transaction['gateway_code']);

        $lockKey = 'payment:transaction:' . $transactionId;

        $result = $this->lock->withLock($lockKey, function () use ($gateway, $transaction, $donation) {
            $verification = $gateway->verify([
                'providerOrderId'       => $transaction['provider_order_id'],
                'providerTransactionId' => $transaction['provider_transaction_id'],
                'merchantOrderId'       => $transaction['merchant_order_id'],
                'expectedAmountMinor'   => (int) $transaction['amount_minor'],
                'expectedCurrency'      => (string) $transaction['currency'],
            ]);

            return $this->statusService->applyVerifiedResult($transaction, $donation, $verification);
        });

        if ($result === null) {
            return ['status' => 'locked', 'changed' => false, 'message' => 'Another process is already working on this transaction.'];
        }

        $this->audit->log('payment.reconciled', 'payment_transaction', (string) $transactionId, [
            'result' => $result['reason'],
            'status' => $result['status'],
            'by'     => $actorUserId,
        ], $actorUserId);

        return [
            'status'  => $result['status'],
            'changed' => $result['changed'],
            'message' => $result['changed']
                ? 'Status updated to ' . $result['status'] . '.'
                : 'No change (' . $result['reason'] . ').',
        ];
    }

    // -------------------------------------------------------------- refunds

    /**
     * Refund a completed donation through the gateway that processed it.
     * Idempotent: a repeated call for the same transaction returns the
     * existing refund rather than issuing a second one.
     *
     * @return array{ok:bool, message:string, refund_id:?int, status:string}
     */
    public function refund(int $transactionId, int $actorUserId, ?int $amountMinor = null): array
    {
        $transaction = $this->payments->find($transactionId);
        if ($transaction === null) {
            return ['ok' => false, 'message' => 'Transaction not found.', 'refund_id' => null, 'status' => 'unknown'];
        }

        $donation = $this->donations->find((int) $transaction['donation_id']);
        if ($donation === null) {
            return ['ok' => false, 'message' => 'Donation not found.', 'refund_id' => null, 'status' => 'unknown'];
        }

        if ((string) $donation['status'] !== 'completed') {
            return ['ok' => false, 'message' => 'Only a completed donation can be refunded.', 'refund_id' => null, 'status' => (string) $donation['status']];
        }

        if ($this->payments->hasCompletedRefund($transactionId)) {
            return ['ok' => false, 'message' => 'This payment has already been refunded.', 'refund_id' => null, 'status' => 'refunded'];
        }

        $idempotencyKey = 'refund:' . (string) $transaction['gateway_code'] . ':' . $transactionId;

        $existing = $this->payments->findRefundByKey($idempotencyKey);
        if ($existing !== null && (string) $existing['status'] === 'completed') {
            return ['ok' => true, 'message' => 'Refund already completed.', 'refund_id' => (int) $existing['id'], 'status' => 'completed'];
        }

        $amount = $amountMinor ?? (int) $donation['amount_minor'];
        if ($amount <= 0 || $amount > (int) $donation['amount_minor']) {
            return ['ok' => false, 'message' => 'Refund amount is not valid.', 'refund_id' => null, 'status' => 'rejected'];
        }

        $gateway = $this->gateways->gateway((string) $transaction['gateway_code']);

        $lockKey = 'refund:transaction:' . $transactionId;

        $outcome = $this->lock->withLock($lockKey, function () use ($gateway, $transaction, $donation, $amount, $idempotencyKey, $actorUserId, $existing) {
            $refundId = $existing !== null
                ? (int) $existing['id']
                : $this->payments->createRefund([
                    'payment_transaction_id' => (int) $transaction['id'],
                    'donation_id'            => (int) $donation['id'],
                    'gateway_id'             => (int) $transaction['gateway_id'],
                    'amount_minor'           => $amount,
                    'currency'               => (string) $donation['currency'],
                    'status'                 => 'pending',
                    'idempotency_key'        => $idempotencyKey,
                    'requested_by'           => $actorUserId,
                ]);

            try {
                /** @var PaymentRefundResult $result */
                $result = $gateway->refund([
                    'providerTransactionId' => $transaction['provider_transaction_id'],
                    'providerOrderId'       => $transaction['provider_order_id'],
                    'amountMinor'           => $amount,
                    'currency'              => (string) $donation['currency'],
                    'merchantOrderId'       => $transaction['merchant_order_id'],
                ]);
            } catch (PaymentGatewayException $e) {
                $this->payments->updateRefund($refundId, [
                    'status'                        => 'failed',
                    'provider_response_description'=> mb_substr($e->getMessage(), 0, 255),
                ]);

                $this->logger->payment('Refund request failed', [
                    'transaction' => (int) $transaction['id'],
                    'reason'      => $e->reason(),
                ]);

                return ['ok' => false, 'message' => $e->getMessage(), 'refund_id' => $refundId, 'status' => 'failed'];
            }

            $this->payments->updateRefund($refundId, [
                'status'                        => $result->isCompleted() ? 'completed' : ($result->status === 'rejected' ? 'rejected' : 'failed'),
                'provider_response_code'        => $result->providerCode,
                'provider_response_description'=> $this->truncate($result->providerMessage),
                'completed_at'                  => $result->isCompleted() ? gmdate('Y-m-d H:i:s') : null,
            ]);

            if ($result->isCompleted()) {
                $freshTransaction = $this->payments->find((int) $transaction['id']) ?? $transaction;
                $freshDonation = $this->donations->find((int) $donation['id']) ?? $donation;

                $this->statusService->markRefunded($freshTransaction, $freshDonation, $amount);

                $this->audit->log('payment.refund_completed', 'payment_transaction', (string) $transaction['id'], [
                    'amount' => $amount,
                    'refund' => $refundId,
                ], $actorUserId);

                return ['ok' => true, 'message' => 'Refund completed.', 'refund_id' => $refundId, 'status' => 'completed'];
            }

            return [
                'ok'       => false,
                'message'  => $result->providerMessage ?? 'The gateway rejected the refund.',
                'refund_id'=> $refundId,
                'status'   => $result->status,
            ];
        });

        if ($outcome === null) {
            return ['ok' => false, 'message' => 'A refund for this payment is already in progress.', 'refund_id' => null, 'status' => 'locked'];
        }

        return $outcome;
    }

    // -------------------------------------------------------------- helpers

    /** Resolve a public donate target: fundraiser slug, campaign slug or team slug. */
    public function resolveTarget(string $slug, ?string $kind = null): ?array
    {
        $slug = trim($slug);

        if ($kind === 'campaign') {
            $campaign = app(\App\Repositories\CampaignRepository::class)->findActiveBySlug($slug);
            return $campaign === null ? null : ['campaign_id' => (int) $campaign['id'], 'kind' => 'campaign', 'record' => $campaign];
        }

        if ($kind === 'team') {
            $team = app(\App\Repositories\TeamRepository::class)->findPublishedBySlug($slug);
            return $team === null ? null : ['team_id' => (int) $team['id'], 'campaign_id' => $team['campaign_id'] ? (int) $team['campaign_id'] : null, 'kind' => 'team', 'record' => $team];
        }

        $fundraiser = app(\App\Repositories\FundraiserRepository::class)->findPublishedBySlug($slug);
        if ($fundraiser !== null) {
            return [
                'fundraiser_id' => (int) $fundraiser['id'],
                'campaign_id'   => $fundraiser['campaign_id'] ? (int) $fundraiser['campaign_id'] : null,
                'kind'          => 'fundraiser',
                'record'        => $fundraiser,
            ];
        }

        $campaign = app(\App\Repositories\CampaignRepository::class)->findActiveBySlug($slug);
        if ($campaign !== null) {
            return ['campaign_id' => (int) $campaign['id'], 'kind' => 'campaign', 'record' => $campaign];
        }

        return null;
    }

    private function buildMerchantOrderId(string $reference): string
    {
        // Alphanumeric only: some gateways reject hyphens and underscores.
        return strtoupper(str_replace('-', '', $reference)) . strtoupper(Str::randomHex(2));
    }

    /** @param array<string,mixed> $target */
    private function describeDonation(array $target): string
    {
        $record = $target['record'] ?? [];
        $title = (string) ($record['title'] ?? $record['name'] ?? 'Almarah Foundation');

        return 'Donation to Almarah Foundation — ' . Str::limit($title, 60, '');
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function sanitiseCallbackPayload(array $payload): array
    {
        $safe = [];
        foreach ($payload as $key => $value) {
            if (!is_scalar($value) && $value !== null) {
                continue;
            }
            if (is_string($key) && preg_match('/pass|secret|token|card|cvv/i', $key)) {
                $safe[$key] = '[REDACTED]';
                continue;
            }
            $safe[(string) $key] = is_string($value) ? mb_substr($value, 0, 255) : $value;
        }
        return $safe;
    }

    /** @return array{outcome:string, donation_id:?int, reference:?string, status:?string, message:string} */
    private function outcome(string $outcome, ?int $donationId, ?string $reference, ?string $status, string $message): array
    {
        return [
            'outcome'      => $outcome,
            'donation_id'  => $donationId,
            'reference'    => $reference,
            'status'       => $status,
            'message'      => $message,
        ];
    }

    private function truncate(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return mb_substr($value, 0, 255);
    }
}
