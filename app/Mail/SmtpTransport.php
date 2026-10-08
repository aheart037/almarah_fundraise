<?php

declare(strict_types=1);

namespace App\Mail;

use App\Core\Logger;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;

/**
 * PHPMailer SMTP transport. mail() is never used.
 * TLS peer verification is on by default and cannot be silently disabled.
 */
final class SmtpTransport implements TransportInterface
{
    public function __construct(private array $config, private Logger $logger)
    {
    }

    private function boot(?string $overrideToEmail = null, ?string $overrideToName = null): PHPMailer
    {
        $mailer = new PHPMailer(true); // true = throw exceptions

        $mailer->isSMTP();
        $mailer->Host       = (string) ($this->config['host'] ?? '');
        $mailer->Port       = (int) ($this->config['port'] ?? 587);
        $mailer->SMTPAuth   = (string) ($this->config['username'] ?? '') !== '';
        $mailer->Username   = (string) ($this->config['username'] ?? '');
        $mailer->Password   = (string) ($this->config['password'] ?? '');
        $mailer->CharSet    = 'UTF-8';
        $mailer->Encoding   = 'base64';
        $mailer->XMailer    = 'Almarah Foundation';

        $encryption = strtolower((string) ($this->config['encryption'] ?? 'tls'));
        if ($encryption === 'ssl' || $encryption === 'smtps') {
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($encryption === 'none' || $encryption === '') {
            $mailer->SMTPSecure = '';
            $mailer->SMTPAutoTLS = false;
        } else {
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        // Certificate verification — never disabled implicitly.
        $mailer->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => (bool) ($this->config['verify_peer'] ?? true),
                'verify_peer_name'  => (bool) ($this->config['verify_peer'] ?? true),
                'allow_self_signed' => false,
            ],
        ];

        if ((bool) ($this->config['debug'] ?? false)) {
            // Debug output goes to the PHP error log, which is protected.
            $mailer->SMTPDebug = 2;
            $mailer->Debugoutput = static function (string $str): void {
                error_log('[smtp] ' . preg_replace('/(pass|password)=[^ ]+/i', '$1=[REDACTED]', $str));
            };
        }

        $from = (string) ($this->config['from_address'] ?? '');
        if ($from === '') {
            throw new RuntimeException('MAIL_FROM_ADDRESS is not configured.');
        }

        $mailer->setFrom($from, (string) ($this->config['from_name'] ?? 'Almarah Foundation'));

        $replyTo = (string) ($this->config['reply_to'] ?? '');
        if ($replyTo !== '') {
            $mailer->addReplyTo($replyTo);
        }

        if ($overrideToEmail !== null) {
            $mailer->addAddress($overrideToEmail, $overrideToName ?? '');
        }

        return $mailer;
    }

    public function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $textBody
    ): bool {
        if ((string) ($this->config['host'] ?? '') === '') {
            throw new RuntimeException('SMTP host is not configured.');
        }
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Recipient email address is not valid.');
        }

        try {
            $mailer = $this->boot($toEmail, $toName);
            $mailer->Subject = $subject;
            $mailer->isHTML(true);
            $mailer->Body    = $htmlBody;
            $mailer->AltBody = $textBody !== '' ? $textBody : strip_tags($htmlBody);

            $sent = $mailer->send();
            $mailer->smtpClose();

            return (bool) $sent;
        } catch (PHPMailerException $e) {
            // PHPMailer messages can include the SMTP dialogue; sanitise them.
            throw new RuntimeException($this->sanitise($e->getMessage()), 0, $e);
        } catch (\Throwable $e) {
            throw new RuntimeException('SMTP transport failure: ' . $this->sanitise($e->getMessage()), 0, $e);
        }
    }

    public function testConnection(): bool
    {
        try {
            $mailer = $this->boot();
            $connected = $mailer->smtpConnect();
            $mailer->smtpClose();
            return (bool) $connected;
        } catch (\Throwable $e) {
            $this->logger->warning('SMTP test connection failed', ['reason' => $this->sanitise($e->getMessage())]);
            return false;
        }
    }

    public function describe(): string
    {
        return sprintf(
            'smtp://%s:%d (%s, auth=%s, verify_peer=%s)',
            (string) ($this->config['host'] ?? 'unset'),
            (int) ($this->config['port'] ?? 0),
            (string) ($this->config['encryption'] ?? 'none'),
            (string) ($this->config['username'] ?? '') !== '' ? 'yes' : 'no',
            (bool) ($this->config['verify_peer'] ?? true) ? 'yes' : 'no'
        );
    }

    /** Remove anything resembling a credential from a transport error string. */
    private function sanitise(string $message): string
    {
        $message = preg_replace('/\b\d{13,19}\b/', '[card-like]', $message) ?? $message;
        $message = preg_replace('/(\bpassword[=:\s]+)[^\s,;]+/i', '$1[REDACTED]', $message) ?? $message;
        return mb_substr(trim($message), 0, 400);
    }
}
