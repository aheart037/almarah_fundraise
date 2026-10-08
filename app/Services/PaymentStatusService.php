<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Mail\Mailer;
use App\Payments\Dtos\PaymentVerificationResult;
use App\Payments\Exceptions\PaymentGatewayException;
use App\Payments\PaymentStatusMapper;
use App\Repositories\DonationRepository;
use App\Repositories\FundraiserRepository;
use App\Repositories\PaymentRepository;

/**
 * The only place where a donation's status may change.
 *
 * Rules enforced here:
 *  - a donation starts as `pending`;
 *  - only a verified provider response can make it `completed`;
 *  - a `completed` donation is never downgraded by a later callback;
 *  - a `refunded` donation is terminal;
 *  - every transition is written to the audit log with its provider code.
 */
final class PaymentStatusService
{
    public function __construct(
        private DonationRepository $donations,
        private PaymentRepository $payments,
        private FundraiserRepository $fundraisers,
        private PaymentStatusMapper $statusMapper,
        private Mailer $mailer,
        private AuditService $audit,
        private Logger $logger
    ) {
    }

    /**
     * Apply a verified provider result to a donation.
     *
     * @param array<string,mixed> $transaction
     * @param array<string,mixed> $donation
     * @return array{changed:bool, status:string, reason:string}
     */
    public function applyVerifiedResult(array $transaction, array $donation, PaymentVerificationResult $result): array
    {
        $current = (string) $donation['status'];
        $target = $result->status;

        if (!$this->statusMapper->isKnownStatus($target)) {
            return ['changed' => false, 'status' => $current, 'reason' => 'unknown_target_status'];
        }

        // Never downgrade a terminal state.
        if ($this->statusMapper->isTerminal($current)) {
            return ['changed' => false, 'status' => $current, 'reason' => 'already_terminal'];
        }

        if (!$result->verified) {
            // Record the provider response for reconciliation without changing
            // the donation's status.
            $this->payments->updateTransaction((int) $transaction['id'], [
                'provider_response_code'        => $result->providerCode,
                'provider_response_description'=> $this->truncate($result->providerMessage),
                'last_reconciled_at'           => gmdate('Y-m-d H:i:s'),
                'status'                       => 'processing',
            ]);

            $this->audit->log('payment.unverified_response', 'payment_transaction', (string) $transaction['id'], [
                'donation' => (string) $donation['public_reference'],
                'message'  => $result->providerMessage,
            ]);

            // Operators read this in the callback log, so prefer the
            // provider's own explanation ("Amount reported by the provider
            // does not match the donation.") over a bare code.
            $reason = trim((string) $result->providerMessage);

            return [
                'changed' => false,
                'status'  => $current,
                'reason'  => $reason !== '' ? $reason : 'unverified',
            ];
        }

        // Only forward progress: a pending donation cannot jump to completed
        // by a stale "processing" response, and vice versa is allowed.
        if ($target === 'processing' || $target === 'pending') {
            if ($current === 'pending') {
                $this->donations->updateStatus((int) $donation['id'], 'processing');
                $this->payments->updateTransaction((int) $transaction['id'], [
                    'status'                        => 'processing',
                    'provider_response_code'        => $result->providerCode,
                    'provider_response_description'=> $this->truncate($result->providerMessage),
                    'last_reconciled_at'           => gmdate('Y-m-d H:i:s'),
                ]);

                $this->notifyDonor($donation, 'pending');

                return ['changed' => true, 'status' => 'processing', 'reason' => 'provider_processing'];
            }

            $this->payments->updateTransaction((int) $transaction['id'], [
                'last_reconciled_at'           => gmdate('Y-m-d H:i:s'),
                'provider_response_code'        => $result->providerCode,
                'provider_response_description'=> $this->truncate($result->providerMessage),
            ]);

            return ['changed' => false, 'status' => $current, 'reason' => 'still_processing'];
        }

        // ---- completed ------------------------------------------------------
        if ($target === 'completed') {
            $this->donations->updateStatus((int) $donation['id'], 'completed', gmdate('Y-m-d H:i:s'));

            $this->payments->updateTransaction((int) $transaction['id'], [
                'status'                        => 'completed',
                'provider_response_code'        => $result->providerCode,
                'provider_response_description'=> $this->truncate($result->providerMessage),
                'provider_transaction_id'       => $result->providerTransactionId ?? $transaction['provider_transaction_id'],
                'provider_order_id'             => $result->providerOrderId ?? $transaction['provider_order_id'],
                'finalized_at'                  => gmdate('Y-m-d H:i:s'),
                'completed_at'                  => gmdate('Y-m-d H:i:s'),
                'last_reconciled_at'            => gmdate('Y-m-d H:i:s'),
            ]);

            $this->audit->log('payment.completed', 'donation', (string) $donation['id'], [
                'reference' => (string) $donation['public_reference'],
                'gateway'   => (string) ($transaction['gateway_code'] ?? ''),
                'amount'    => (int) $donation['amount_minor'],
                'currency'  => (string) $donation['currency'],
            ]);

            $this->logger->payment('Donation completed', [
                'reference' => (string) $donation['public_reference'],
                'gateway'   => (string) ($transaction['gateway_code'] ?? ''),
            ]);

            $this->sendReceipt($donation, (string) ($transaction['gateway_code'] ?? ''));
            $this->notifyFundraiserOwner($donation);

            return ['changed' => true, 'status' => 'completed', 'reason' => 'verified_completed'];
        }

        // ---- failed / cancelled / abandoned ---------------------------------
        if (in_array($target, ['failed', 'cancelled', 'abandoned'], true)) {
            $this->donations->updateStatus((int) $donation['id'], $target);

            $this->payments->updateTransaction((int) $transaction['id'], [
                'status'                        => $target,
                'provider_response_code'        => $result->providerCode,
                'provider_response_description'=> $this->truncate($result->providerMessage),
                'finalized_at'                  => gmdate('Y-m-d H:i:s'),
                'last_reconciled_at'            => gmdate('Y-m-d H:i:s'),
            ]);

            $this->audit->log('payment.' . $target, 'donation', (string) $donation['id'], [
                'reference' => (string) $donation['public_reference'],
                'code'      => $result->providerCode,
            ]);

            $this->notifyDonor($donation, $target);

            return ['changed' => true, 'status' => $target, 'reason' => 'verified_' . $target];
        }

        // ---- refunded (set by the refund service, not by a status check) ----
        if ($target === 'refunded') {
            $this->donations->updateStatus((int) $donation['id'], 'refunded');
            $this->payments->updateTransaction((int) $transaction['id'], ['status' => 'refunded']);
            $this->notifyDonor($donation, 'refunded');

            return ['changed' => true, 'status' => 'refunded', 'reason' => 'refunded'];
        }

        return ['changed' => false, 'status' => $current, 'reason' => 'no_change'];
    }

    /**
     * Mark a donation as abandoned (donor never returned from the gateway and
     * the provider has no record of payment).
     *
     * @param array<string,mixed> $transaction
     * @param array<string,mixed> $donation
     */
    public function markAbandoned(array $transaction, array $donation, string $reason): bool
    {
        if (in_array((string) $donation['status'], ['completed', 'refunded'], true)) {
            return false;
        }

        $this->donations->updateStatus((int) $donation['id'], 'abandoned');
        $this->payments->updateTransaction((int) $transaction['id'], [
            'status'             => 'abandoned',
            'last_reconciled_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $this->audit->log('payment.abandoned', 'donation', (string) $donation['id'], ['reason' => $reason]);

        return true;
    }

    /**
     * Mark a refunded donation. Only called after a gateway confirmed the
     * refund with a success response.
     *
     * @param array<string,mixed> $transaction
     * @param array<string,mixed> $donation
     */
    public function markRefunded(array $transaction, array $donation, int $amountMinor): void
    {
        if ((string) $donation['status'] === 'refunded') {
            return;
        }

        if ((string) $donation['status'] !== 'completed') {
            throw new PaymentGatewayException(
                'Only a completed donation can be refunded.',
                (string) ($transaction['gateway_code'] ?? ''),
                'invalid_state'
            );
        }

        $this->donations->updateStatus((int) $donation['id'], 'refunded');
        $this->payments->updateTransaction((int) $transaction['id'], [
            'status'      => 'refunded',
            'refunded_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $this->audit->log('payment.refunded', 'donation', (string) $donation['id'], [
            'reference' => (string) $donation['public_reference'],
            'amount'    => $amountMinor,
            'currency'  => (string) $donation['currency'],
        ]);

        $this->notifyDonor($donation, 'refunded');
    }

    // ------------------------------------------------------------------ mail

    /** @param array<string,mixed> $donation */
    private function sendReceipt(array $donation, string $gatewayCode): void
    {
        $email = (string) ($donation['donor_email'] ?? '');
        if ($email === '') {
            return;
        }

        $this->mailer->donationReceipt(
            $email,
            (string) ($donation['donor_name'] ?? 'Friend'),
            $donation,
            (string) ($donation['fundraiser_title'] ?? 'Almarah Foundation'),
            $this->gatewayLabel($gatewayCode)
        );

        $this->donations->setReceiptStatus((int) $donation['id'], 'queued');
    }

    /** @param array<string,mixed> $donation */
    private function notifyDonor(array $donation, string $status): void
    {
        $email = (string) ($donation['donor_email'] ?? '');
        if ($email === '') {
            return;
        }

        $name = (string) ($donation['donor_name'] ?? 'Friend');
        $gateway = $this->gatewayLabel((string) ($donation['gateway_code'] ?? ''));

        match ($status) {
            'pending'   => $this->mailer->donationPending($email, $name, $donation, $gateway),
            'failed'    => $this->mailer->donationFailed($email, $name, $donation, $gateway),
            'cancelled' => $this->mailer->donationCancelled($email, $name, $donation, $gateway),
            'refunded'  => $this->mailer->donationRefunded($email, $name, $donation, $gateway),
            default     => null,
        };
    }

    /** @param array<string,mixed> $donation */
    private function notifyFundraiserOwner(array $donation): void
    {
        $fundraiserId = (int) ($donation['fundraiser_id'] ?? 0);
        if ($fundraiserId <= 0) {
            return;
        }

        $fundraiser = $this->fundraisers->find($fundraiserId);
        if ($fundraiser === null) {
            return;
        }

        $ownerEmail = (string) ($fundraiser['owner_email'] ?? '');
        if ($ownerEmail === '') {
            return;
        }

        $donorLabel = ((int) ($donation['anonymous'] ?? 0) === 1)
            ? 'An anonymous supporter'
            : (string) ($donation['donor_name'] ?? 'A supporter');

        $fundraiser['_donation_id'] = (int) $donation['id'];

        $this->mailer->newDonation(
            $ownerEmail,
            (string) ($fundraiser['first_name'] ?? 'there'),
            $fundraiser,
            money((int) $donation['amount_minor'], (string) $donation['currency']),
            $donorLabel
        );

        // Goal reached is a one-off notification per fundraiser.
        $goal = (int) ($fundraiser['goal_minor'] ?? 0);
        $raised = (int) ($fundraiser['raised_minor'] ?? 0);

        if ($goal > 0 && $raised >= $goal) {
            $this->mailer->goalReached(
                $ownerEmail,
                (string) ($fundraiser['first_name'] ?? 'there'),
                $fundraiser
            );
        }
    }

    private function gatewayLabel(string $code): string
    {
        return match ($code) {
            'meezan'   => 'Meezan Bank',
            'etisalat' => 'Etisalat / UBL EPG',
            default    => 'Payment gateway',
        };
    }

    private function truncate(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return mb_substr($value, 0, 255);
    }
}
