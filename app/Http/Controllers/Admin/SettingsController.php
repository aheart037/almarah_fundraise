<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Csrf;
use App\Mail\TransportInterface;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Http\Controllers\Controller;
use App\Mail\MailQueue;
use App\Mail\Mailer;
use App\Payments\PaymentGatewayManager;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\SettingsService;

/**
 * Platform settings: gateway credentials, SMTP credentials and site options.
 *
 * Secrets are written through SettingsService, which encrypts them at rest.
 * A stored secret is never sent back to the browser — the form shows a
 * "configured" state and an empty field instead.
 */
final class SettingsController extends Controller
{
    /** Keys an administrator may edit on the site tab. */
    private const SITE_KEYS = [
        'site.tagline',
        'site.support_email',
        'site.support_phone',
        'site.footer_note',
        'moderation.require_email_verification',
        'moderation.auto_publish_updates',
        'donations.min_minor',
        'donations.max_minor',
        'donations.receipts_from',
    ];

    public function __construct(
        View $view,
        Session $session,
        Csrf $csrf,
        AuthService $auth,
        private SettingsService $settings,
        private AuditService $audit,
        private PaymentGatewayManager $gateways,
        private MailQueue $queue,
        private Mailer $mailer,
        private TransportInterface $transport
    ) {
        parent::__construct($view, $session, $csrf, $auth);
    }

    public function index(): Response
    {
        $gatewayCodes = $this->gateways->availableCodes();

        $gatewayForms = [];
        foreach ($gatewayCodes as $code) {
            $config = $this->settings->gatewayConfig($code);

            // Never hand the stored secret to the view.
            unset($config['password']);
            $config['password_configured'] = $this->settings->hasSecret('gateway.' . $code . '.password');

            $gatewayForms[$code] = $config;
        }

        return $this->render('admin/settings/index', [
            'pageTitle'    => 'Settings',
            'gateways'     => $gatewayForms,
            'gatewayStatus'=> $this->settings->gatewayStatus(),
            'mail'         => [
                'host'            => $this->settings->get('mail.host', ''),
                'port'            => $this->settings->get('mail.port', ''),
                'encryption'      => $this->settings->get('mail.encryption', ''),
                'username'        => $this->settings->get('mail.username', ''),
                'password_configured' => $this->settings->hasSecret('mail.password'),
                'from_address'    => $this->settings->get('mail.from_address', ''),
                'from_name'       => $this->settings->get('mail.from_name', ''),
                'reply_to'        => $this->settings->get('mail.reply_to', ''),
                'verify_peer'     => $this->settings->bool('mail.verify_peer', true),
                'queue_enabled'   => $this->queue->queueEnabled(),
            ],
            'mailConfigured' => $this->settings->mailConfigured(),
            'site'         => [
                'tagline'        => $this->settings->get('site.tagline', ''),
                'support_email'  => $this->settings->get('site.support_email', ''),
                'support_phone'  => $this->settings->get('site.support_phone', ''),
                'footer_note'    => $this->settings->get('site.footer_note', ''),
                'require_verification' => $this->settings->bool('moderation.require_email_verification', false),
                'auto_publish_updates' => $this->settings->bool('moderation.auto_publish_updates', true),
                'min_minor'      => $this->settings->int('donations.min_minor', 10000),
                'max_minor'      => $this->settings->int('donations.max_minor', 500000000),
                'receipts_from'  => $this->settings->get('donations.receipts_from', ''),
            ],
            'queueStats'   => $this->queue->stats(),
            'secretKeys'   => [
                'mail.password',
                'gateway.meezan.password',
                'gateway.etisalat.password',
            ],
        ], 'layouts/admin');
    }

    public function saveSite(Request $request): Response
    {
        $min = (string) $request->input('donations_min', '');
        $max = (string) $request->input('donations_max', '');

        $minMinor = $min !== '' ? (int) round(((float) str_replace(',', '', $min)) * 100) : null;
        $maxMinor = $max !== '' ? (int) round(((float) str_replace(',', '', $max)) * 100) : null;

        if ($minMinor !== null && $maxMinor !== null && $minMinor > $maxMinor) {
            $this->flashError('The minimum donation cannot be larger than the maximum.');
            return $this->redirect('/admin/settings');
        }

        $this->settings->setMany([
            'site.tagline'                          => (string) $request->input('tagline', ''),
            'site.support_email'                    => (string) $request->input('support_email', ''),
            'site.support_phone'                    => (string) $request->input('support_phone', ''),
            'site.footer_note'                      => (string) $request->input('footer_note', ''),
            'moderation.require_email_verification' => $request->bool('require_verification') ? '1' : '0',
            'moderation.auto_publish_updates'       => $request->bool('auto_publish_updates') ? '1' : '0',
            'donations.receipts_from'               => (string) $request->input('receipts_from', ''),
        ], (int) $this->auth->id());

        if ($minMinor !== null && $maxMinor !== null) {
            $this->settings->setMany([
                'donations.min_minor' => (string) $minMinor,
                'donations.max_minor' => (string) $maxMinor,
            ], (int) $this->auth->id());
        }

        $this->audit->log('settings.site_updated', 'settings', 'site', [], (int) $this->auth->id());

        $this->flashSuccess('Site settings saved.');
        return $this->redirect('/admin/settings');
    }

    public function saveGateway(Request $request): Response
    {
        $code = strtolower((string) $request->routeParam('code', ''));

        if (!in_array($code, $this->gateways->availableCodes(), true)) {
            abort(404, 'Unknown payment gateway.');
        }

        $existing = $this->settings->gatewayConfig($code);

        $fields = [];

        // Only known fields are accepted; anything else is ignored.
        foreach (['enabled', 'environment', 'username', 'merchant_id', 'customer', 'store', 'terminal',
                  'currency_code', 'currency_name', 'currency', 'timeout', 'sandbox_url', 'live_url'] as $field) {
            if (!array_key_exists($field, $existing) && $field !== 'enabled' && $field !== 'environment') {
                continue;
            }
            if ($field === 'enabled') {
                $fields['gateway.' . $code . '.enabled'] = $request->bool('enabled') ? '1' : '0';
                continue;
            }
            if (!array_key_exists($field, $request->all())) {
                continue;
            }
            $fields['gateway.' . $code . '.' . $field] = (string) $request->input($field, '');
        }

        $env = (string) $request->input('environment', 'sandbox');
        $fields['gateway.' . $code . '.environment'] = in_array($env, ['sandbox', 'live'], true) ? $env : 'sandbox';

        // An empty secret field means "keep the stored one".
        $password = (string) $request->input('password', '');
        $encrypted = [];
        if ($password !== '') {
            $fields['gateway.' . $code . '.password'] = $password;
            $encrypted[] = 'gateway.' . $code . '.password';
        }

        $this->settings->setMany($fields, (int) $this->auth->id(), $encrypted);

        $this->audit->log('settings.gateway_updated', 'gateway', $code, [
            'fields'      => array_keys($fields),
            'environment' => $env,
        ], (int) $this->auth->id());

        $this->flashSuccess(ucfirst($code) . ' settings saved.');
        return $this->redirect('/admin/settings#gateway-' . $code);
    }

    /** Live connectivity check against the gateway — no payment is created. */
    public function testGateway(Request $request): Response
    {
        $code = strtolower((string) $request->routeParam('code', ''));

        if (!in_array($code, $this->gateways->availableCodes(), true)) {
            abort(404, 'Unknown payment gateway.');
        }

        $status = $this->settings->gatewayStatus();

        if (empty($status[$code]['configured'])) {
            $this->flashError('Add the merchant credentials before testing this gateway.');
            return $this->redirect('/admin/settings#gateway-' . $code);
        }

        try {
            $gateway = $this->gateways->gateway($code);
            $credentialCheck = method_exists($gateway, 'credentialsValid')
                ? (bool) $gateway->credentialsValid()
                : true;
        } catch (\Throwable $e) {
            $this->audit->log('settings.gateway_test_failed', 'gateway', $code, ['error' => $e->getMessage()], (int) $this->auth->id());
            $this->flashError('The gateway rejected our configuration: ' . $e->getMessage());
            return $this->redirect('/admin/settings#gateway-' . $code);
        }

        $this->audit->log('settings.gateway_tested', 'gateway', $code, ['ok' => $credentialCheck], (int) $this->auth->id());

        if ($credentialCheck) {
            $this->flashSuccess(ucfirst($code) . ' credentials look correct and the endpoint is reachable.');
        } else {
            $this->flashError(ucfirst($code) . ' rejected our credentials. Check the username and password.');
        }

        return $this->redirect('/admin/settings#gateway-' . $code);
    }

    public function saveSmtp(Request $request): Response
    {
        $host = trim((string) $request->input('host', ''));
        $port = (int) $request->input('port', 587);
        $encryption = (string) $request->input('encryption', 'tls');
        $fromAddress = trim((string) $request->input('from_address', ''));

        if ($fromAddress !== '' && filter_var($fromAddress, FILTER_VALIDATE_EMAIL) === false) {
            $this->flashError('The from address must be a valid email address.');
            return $this->redirect('/admin/settings#smtp');
        }

        if ($host !== '' && ($port < 1 || $port > 65535)) {
            $this->flashError('The SMTP port must be between 1 and 65535.');
            return $this->redirect('/admin/settings#smtp');
        }

        $fields = [
            'mail.host'         => $host,
            'mail.port'         => (string) $port,
            'mail.encryption'   => in_array($encryption, ['tls', 'ssl', 'none'], true) ? $encryption : 'tls',
            'mail.username'     => (string) $request->input('username', ''),
            'mail.from_address' => $fromAddress,
            'mail.from_name'    => (string) $request->input('from_name', ''),
            'mail.reply_to'     => (string) $request->input('reply_to', ''),
            'mail.verify_peer'  => $request->bool('verify_peer', true) ? '1' : '0',
            'mail.queue_enabled'=> $request->bool('queue_enabled', true) ? '1' : '0',
        ];

        $encrypted = [];
        $password = (string) $request->input('password', '');
        if ($password !== '') {
            $fields['mail.password'] = $password;
            $encrypted[] = 'mail.password';
        }

        $this->settings->setMany($fields, (int) $this->auth->id(), $encrypted);

        $this->audit->log('settings.smtp_updated', 'settings', 'smtp', ['fields' => array_keys($fields)], (int) $this->auth->id());

        $this->flashSuccess('SMTP settings saved.');
        return $this->redirect('/admin/settings#smtp');
    }

    /** Opens a real SMTP connection and authenticates, without sending mail. */
    public function testSmtp(): Response
    {
        if (!$this->settings->mailConfigured()) {
            $this->flashError('Add an SMTP host and a from address before testing.');
            return $this->redirect('/admin/settings#smtp');
        }

        try {
            $ok = $this->transport->testConnection();
        } catch (\Throwable $e) {
            $this->audit->log('settings.smtp_test_failed', 'settings', 'smtp', ['error' => $e->getMessage()], (int) $this->auth->id());
            $this->flashError('SMTP connection failed: ' . $e->getMessage());
            return $this->redirect('/admin/settings#smtp');
        }

        $this->audit->log('settings.smtp_tested', 'settings', 'smtp', ['ok' => $ok], (int) $this->auth->id());

        if ($ok) {
            $this->flashSuccess('Connected to the mail server and authenticated successfully.');
        } else {
            $this->flashError('The mail server refused the connection. Check the host, port, encryption and credentials.');
        }

        return $this->redirect('/admin/settings#smtp');
    }

    public function sendTestEmail(Request $request): Response
    {
        $recipient = trim((string) $request->input('recipient', ''));

        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            $this->flashError('Enter a valid email address to send the test to.');
            return $this->redirect('/admin/settings#smtp');
        }

        // Rendered on demand and never echoed back, so a stored password can
        // never be read out of the admin screen.
        $sent = $this->mailer->sendRaw(
            $recipient,
            'Almarah Foundation — SMTP test',
            '<p>This is a test message from the Almarah Foundation fundraising platform.</p>'
            . '<p>If you received it, outbound email is working. '
            . 'Sent ' . gmdate('j F Y H:i') . ' UTC by ' . e((string) $this->auth->user()['email']) . '.</p>',
            'This is a test message from the Almarah Foundation fundraising platform. If you received it, outbound email is working.'
        );

        if ($sent) {
            $this->flashSuccess('Test email sent to ' . $recipient . '.');
        } else {
            $this->flashError('The test email could not be sent. Check the SMTP settings and the email queue for the error.');
        }

        return $this->redirect('/admin/settings#smtp');
    }
}
