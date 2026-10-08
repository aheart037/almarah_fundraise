<?php

declare(strict_types=1);

namespace App\Mail;

use App\Core\Logger;
use App\Services\EmailTemplateService;

/**
 * Event-level API used by services and controllers. Each method maps a domain
 * event to a queued email with an idempotent dedupe key.
 */
final class Mailer
{
    public function __construct(
        private MailQueue $queue,
        private EmailTemplateService $templates,
        private Logger $logger
    ) {
    }

    /** Keep email addresses out of logs. */
    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return '***';
        }
        $local = $parts[0];
        $visible = mb_substr($local, 0, 1);
        return $visible . str_repeat('*', max(1, mb_strlen($local) - 1)) . '@' . $parts[1];
    }

    /** @return array{subject:string,html:string,text:string} */
    public function preview(string $eventKey, array $data = []): array
    {
        return $this->templates->render($eventKey, $data);
    }

    /**
     * Send a one-off message straight through the queue.
     *
     * Used by the admin "send test email" button. The body is composed by the
     * caller and is never stored in a template, so a saved SMTP password can
     * never be read back out of the interface.
     */
    public function sendRaw(string $toEmail, string $subject, string $htmlBody, string $textBody): bool
    {
        $jobId = $this->queue->enqueueRaw($toEmail, $toEmail, $subject, $htmlBody, $textBody);

        if ($jobId === null) {
            $this->logger->warning('Test email could not be queued', [
                'to' => $this->maskEmail($toEmail),
            ]);
            return false;
        }

        // When the queue is disabled we are expected to deliver inline; the
        // worker path returns false if delivery fails.
        return $this->queue->processJobNow($jobId);
    }

    // ---------------------------------------------------------------- account

    public function accountCreated(string $email, string $name, int $userId): void
    {
        $this->queue->enqueue('account.created', $email, $name, [
            'heading'    => 'Welcome to Almarah Foundation fundraising',
            'donor_name' => $name,
            'body'       => 'Your Almarah Foundation account is ready. You can start a fundraiser, join a team and track your impact from your dashboard.',
            'cta_label'  => 'Open your dashboard',
            'cta_url'    => $this->dashboardUrl(),
        ], "account.created:{$userId}");
    }

    public function emailVerification(string $email, string $name, string $tokenUrl, int $userId): void
    {
        $this->queue->enqueue('account.verify_email', $email, $name, [
            'heading'    => 'Verify your email address',
            'donor_name' => $name,
            'body'       => 'Please confirm your email address so we can keep your fundraiser secure and send you donation receipts.',
            'cta_label'  => 'Verify my email',
            'cta_url'    => $tokenUrl,
            'expires'    => '48 hours',
        ], "account.verify:{$userId}:" . substr(hash('sha256', $tokenUrl), 0, 16));
    }

    public function passwordReset(string $email, string $name, string $tokenUrl, int $userId): void
    {
        $this->queue->enqueue('account.password_reset', $email, $name, [
            'heading'    => 'Reset your password',
            'donor_name' => $name,
            'body'       => 'We received a request to reset your Almarah Foundation password. If this was not you, you can ignore this email — your password will not change.',
            'cta_label'  => 'Choose a new password',
            'cta_url'    => $tokenUrl,
            'expires'    => '2 hours',
        ], "account.reset:{$userId}:" . substr(hash('sha256', $tokenUrl), 0, 16));
    }

    public function passwordChanged(string $email, string $name, int $userId, string $when): void
    {
        $this->queue->enqueue('account.password_changed', $email, $name, [
            'heading'    => 'Your password was changed',
            'donor_name' => $name,
            'body'       => "Your Almarah Foundation password was changed on {$when}. If this wasn't you, contact us immediately at info@almarah.org.",
            'cta_label'  => 'Contact support',
            'cta_url'    => $this->supportUrl(),
        ], "account.pwchanged:{$userId}:{$when}");
    }

    public function accountSuspended(string $email, string $name, string $reason): void
    {
        $this->queue->enqueue('account.suspended', $email, $name, [
            'heading'    => 'Your account has been suspended',
            'donor_name' => $name,
            'body'       => "Your account has been suspended. Reason: {$reason}. Reply to this email if you believe this is a mistake.",
            'cta_label'  => 'Contact support',
            'cta_url'    => $this->supportUrl(),
        ], 'account.suspended:' . strtolower($email) . ':' . substr(hash('sha256', $reason), 0, 8));
    }

    public function accountReactivated(string $email, string $name): void
    {
        $this->queue->enqueue('account.reactivated', $email, $name, [
            'heading'    => 'Your account is active again',
            'donor_name' => $name,
            'body'       => 'Your Almarah Foundation account has been reactivated. Welcome back — your fundraisers are available again.',
            'cta_label'  => 'Go to your dashboard',
            'cta_url'    => $this->dashboardUrl(),
        ], 'account.reactivated:' . strtolower($email));
    }

    // ------------------------------------------------------------- fundraiser

    public function fundraiserSubmitted(string $email, string $name, array $f): void
    {
        $this->queue->enqueue('fundraiser.submitted', $email, $name, [
            'heading'          => 'We received your fundraiser',
            'donor_name'       => $name,
            'fundraiser_title' => (string) $f['title'],
            'body'             => 'Thank you for fundraising for Almarah Foundation. Our team reviews new pages to keep the platform safe — you will hear from us shortly.',
            'cta_label'        => 'View your fundraiser',
            'cta_url'          => $this->fundraiserUrl($f),
        ], 'fundraiser.submitted:' . $f['id'] . ':' . $f['updated_at']);
    }

    public function fundraiserApproved(string $email, string $name, array $f): void
    {
        $this->queue->enqueue('fundraiser.approved', $email, $name, [
            'heading'          => 'Your fundraiser is approved',
            'donor_name'       => $name,
            'fundraiser_title' => (string) $f['title'],
            'body'             => 'Great news — your fundraiser has been approved and is now visible to supporters. Share your link to start raising.',
            'cta_label'        => 'Share your fundraiser',
            'cta_url'          => $this->fundraiserUrl($f),
        ], 'fundraiser.approved:' . $f['id'] . ':' . ($f['published_at'] ?? $f['updated_at']));
    }

    public function fundraiserRejected(string $email, string $name, array $f, string $reason): void
    {
        $this->queue->enqueue('fundraiser.rejected', $email, $name, [
            'heading'          => 'Your fundraiser needs a change',
            'donor_name'       => $name,
            'fundraiser_title' => (string) $f['title'],
            'body'             => "Our team reviewed your fundraiser and asked for a change:\n\n{$reason}\n\nUpdate your page and resubmit — we will review it again straight away.",
            'cta_label'        => 'Edit your fundraiser',
            'cta_url'          => $this->fundraiserEditUrl($f),
        ], 'fundraiser.rejected:' . $f['id'] . ':' . substr(hash('sha256', $reason), 0, 10));
    }

    public function fundraiserPaused(string $email, string $name, array $f, string $reason): void
    {
        $this->queue->enqueue('fundraiser.paused', $email, $name, [
            'heading'          => 'Your fundraiser has been paused',
            'donor_name'       => $name,
            'fundraiser_title' => (string) $f['title'],
            'body'             => "Your fundraiser is paused and no longer accepting donations.\n\nReason: {$reason}",
            'cta_label'        => 'Contact support',
            'cta_url'          => $this->supportUrl(),
        ], 'fundraiser.paused:' . $f['id'] . ':' . substr(hash('sha256', $reason), 0, 10));
    }

    public function fundraiserUpdatePublished(string $email, string $name, array $f, string $updateTitle): void
    {
        $this->queue->enqueue('fundraiser.update_published', $email, $name, [
            'heading'          => 'New update published',
            'donor_name'       => $name,
            'fundraiser_title' => (string) $f['title'],
            'body'             => "You published a new update: \"{$updateTitle}\". Supporters can see it on your fundraiser page.",
            'cta_label'        => 'View your fundraiser',
            'cta_url'          => $this->fundraiserUrl($f),
        ], 'fundraiser.update:' . $f['id'] . ':' . substr(hash('sha256', $updateTitle), 0, 10));
    }

    public function newDonation(string $email, string $name, array $f, string $amount, string $donorName): void
    {
        $this->queue->enqueue('fundraiser.new_donation', $email, $name, [
            'heading'          => 'You received a donation',
            'donor_name'       => $name,
            'fundraiser_title' => (string) $f['title'],
            'amount'           => $amount,
            'supporter_name'   => $donorName,
            'body'             => "{$donorName} donated {$amount} to your fundraiser. Log in to see your updated total and say thank you.",
            'cta_label'        => 'View your donations',
            'cta_url'          => $this->fundraiserDonationsUrl($f),
        ], 'donation.new:' . ($f['_donation_id'] ?? $f['id']) . ':' . substr(hash('sha256', $amount), 0, 6));
    }

    public function goalReached(string $email, string $name, array $f): void
    {
        $this->queue->enqueue('fundraiser.goal_reached', $email, $name, [
            'heading'          => 'You reached your goal!',
            'donor_name'       => $name,
            'fundraiser_title' => (string) $f['title'],
            'body'             => 'Congratulations — your fundraiser has reached its goal. Every extra rupee now funds even more of our work. Consider raising your goal and keeping going.',
            'cta_label'        => 'View your fundraiser',
            'cta_url'          => $this->fundraiserUrl($f),
        ], 'fundraiser.goal:' . $f['id']);
    }

    public function fundraiserEndingSoon(string $email, string $name, array $f, int $days): void
    {
        $this->queue->enqueue('fundraiser.ending_soon', $email, $name, [
            'heading'          => 'Your fundraiser is ending soon',
            'donor_name'       => $name,
            'fundraiser_title' => (string) $f['title'],
            'days_left'        => (string) $days,
            'body'             => "Your fundraiser ends in {$days} days. A short update to your supporters is the most effective way to finish strong.",
            'cta_label'        => 'Post an update',
            'cta_url'          => $this->fundraiserUrl($f),
        ], 'fundraiser.ending:' . $f['id'] . ':' . gmdate('Y-m-d'));
    }

    // ------------------------------------------------------------------- team

    public function teamInvitation(string $email, string $inviteeName, array $team, string $inviterName, string $acceptUrl): void
    {
        $this->queue->enqueue('team.invitation', $email, $inviteeName, [
            'heading'    => 'You have been invited to a fundraising team',
            'donor_name' => $inviteeName,
            'team_name'  => (string) $team['name'],
            'inviter'    => $inviterName,
            'body'       => "{$inviterName} invited you to join \"{$team['name']}\" on Almarah Foundation fundraising. Join the team and start raising together.",
            'cta_label'  => 'Join the team',
            'cta_url'    => $acceptUrl,
        ], 'team.invite:' . $team['id'] . ':' . strtolower($email) . ':' . substr(hash('sha256', $acceptUrl), 0, 12));
    }

    public function teamMemberJoined(string $email, string $ownerName, array $team, string $memberName): void
    {
        $this->queue->enqueue('team.member_joined', $email, $ownerName, [
            'heading'     => 'A new member joined your team',
            'donor_name'  => $ownerName,
            'team_name'   => (string) $team['name'],
            'member_name' => $memberName,
            'body'        => "{$memberName} has joined \"{$team['name']}\". Welcome them and help them get started with their own page.",
            'cta_label'   => 'Manage your team',
            'cta_url'     => $this->teamUrl($team),
        ], 'team.joined:' . $team['id'] . ':' . substr(hash('sha256', $memberName), 0, 12));
    }

    // ---------------------------------------------------------------- payment

    public function donationReceipt(string $email, string $name, array $donation, string $fundraiserTitle, string $gatewayLabel): void
    {
        $this->queue->enqueue('donation.receipt', $email, $name, [
            'heading'          => 'Thank you for your donation',
            'donor_name'       => $name,
            'reference'        => (string) $donation['public_reference'],
            'amount'           => money((int) $donation['amount_minor'], (string) $donation['currency']),
            'currency'         => (string) $donation['currency'],
            'fundraiser_title' => $fundraiserTitle,
            'gateway'          => $gatewayLabel,
            'status'           => 'Completed',
            'receipt_date'     => gmdate('j F Y'),
            'body'             => 'Your donation has been received. This email is your official receipt — please keep it for your records.',
            'cta_label'        => 'View the fundraiser',
            'cta_url'          => $donation['_fundraiser_url'] ?? base_url('fundraisers'),
        ], 'donation.receipt:' . $donation['id']);
    }

    public function donationPending(string $email, string $name, array $donation, string $gatewayLabel): void
    {
        $this->queue->enqueue('donation.pending', $email, $name, [
            'heading'    => 'Your donation is being processed',
            'donor_name' => $name,
            'reference'  => (string) $donation['public_reference'],
            'amount'     => money((int) $donation['amount_minor'], (string) $donation['currency']),
            'gateway'    => $gatewayLabel,
            'status'     => 'Processing',
            'body'       => 'We have not yet received final confirmation from the payment provider. We will email your receipt as soon as it is confirmed — no action is needed from you.',
            'cta_label'  => 'Check the status',
            'cta_url'    => $this->donationUrl($donation),
        ], 'donation.pending:' . $donation['id']);
    }

    public function donationFailed(string $email, string $name, array $donation, string $gatewayLabel): void
    {
        $this->queue->enqueue('donation.failed', $email, $name, [
            'heading'    => 'Your donation could not be completed',
            'donor_name' => $name,
            'reference'  => (string) $donation['public_reference'],
            'amount'     => money((int) $donation['amount_minor'], (string) $donation['currency']),
            'gateway'    => $gatewayLabel,
            'status'     => 'Failed',
            'body'       => 'The payment was not completed, so no money has left your account. You are welcome to try again — the children of Almarah would be grateful.',
            'cta_label'  => 'Try again',
            'cta_url'    => $donation['_retry_url'] ?? base_url('fundraisers'),
        ], 'donation.failed:' . $donation['id']);
    }

    public function donationCancelled(string $email, string $name, array $donation, string $gatewayLabel): void
    {
        $this->queue->enqueue('donation.cancelled', $email, $name, [
            'heading'    => 'Your donation was cancelled',
            'donor_name' => $name,
            'reference'  => (string) $donation['public_reference'],
            'amount'     => money((int) $donation['amount_minor'], (string) $donation['currency']),
            'gateway'    => $gatewayLabel,
            'status'     => 'Cancelled',
            'body'       => 'Your donation was cancelled at the payment page and no money has been taken. You can start again whenever you are ready.',
            'cta_label'  => 'Start again',
            'cta_url'    => base_url('fundraisers'),
        ], 'donation.cancelled:' . $donation['id']);
    }

    public function donationRefunded(string $email, string $name, array $donation, string $gatewayLabel): void
    {
        $this->queue->enqueue('donation.refunded', $email, $name, [
            'heading'    => 'Your donation has been refunded',
            'donor_name' => $name,
            'reference'  => (string) $donation['public_reference'],
            'amount'     => money((int) $donation['amount_minor'], (string) $donation['currency']),
            'gateway'    => $gatewayLabel,
            'status'     => 'Refunded',
            'body'       => 'We have processed a refund for your donation. Depending on your bank, the amount may take several working days to appear on your statement.',
            'cta_label'  => 'View your donation status',
            'cta_url'    => $this->donationUrl($donation),
        ], 'donation.refunded:' . $donation['id']);
    }

    public function adminPaymentAlert(array $donation, string $reason): void
    {
        $to = (string) (config('app.org.email') ?? '');
        if ($to === '') {
            return;
        }

        $this->queue->enqueue('admin.payment_alert', $to, 'Almarah Admin', [
            'heading'    => 'Payment requires attention',
            'reference'  => (string) $donation['public_reference'],
            'amount'     => money((int) $donation['amount_minor'], (string) $donation['currency']),
            'body'       => "A payment needs manual reconciliation:\n\n{$reason}",
            'cta_label'  => 'Open the admin dashboard',
            'cta_url'    => base_url('admin/donations'),
        ], 'admin.alert:' . $donation['id'] . ':' . substr(hash('sha256', $reason), 0, 10));
    }

    // ------------------------------------------------------------------ urls

    /** Public status page for a donation, keyed by its public reference. */
    private function donationUrl(array $donation): string
    {
        return base_url('donation/' . ($donation['public_reference'] ?? ''));
    }

    private function fundraiserUrl(array $f): string
    {
        return base_url('fundraisers/' . ($f['slug'] ?? ''));
    }

    private function fundraiserEditUrl(array $f): string
    {
        return base_url('dashboard/fundraisers/' . ($f['id'] ?? '') . '/edit');
    }

    private function fundraiserDonationsUrl(array $f): string
    {
        return base_url('dashboard/fundraisers/' . ($f['id'] ?? '') . '/donations');
    }

    private function teamUrl(array $t): string
    {
        return base_url('dashboard/teams/' . ($t['id'] ?? ''));
    }

    private function dashboardUrl(): string
    {
        return base_url('dashboard');
    }

    private function supportUrl(): string
    {
        return base_url('support');
    }
}
