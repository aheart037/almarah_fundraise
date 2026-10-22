<?php

declare(strict_types=1);

namespace App\Mail;

use App\Core\Database;
use App\Core\Logger;
use App\Services\EmailTemplateService;
use Throwable;

/**
 * Durable email queue.
 *
 * Guarantees:
 *  - a job with a dedupe_key is never queued twice;
 *  - a job is claimed atomically before sending, so two workers cannot send
 *    the same message;
 *  - a successful send is recorded before any retry can occur, so a retry
 *    never duplicates an email that already went out.
 */
final class MailQueue
{
    private bool $drainScheduled = false;

    public function __construct(
        private Database $db,
        private TransportInterface $transport,
        private EmailTemplateService $templates,
        private Logger $logger,
        private array $config = []
    ) {
    }

    /**
     * Queue an email job.
     *
     * @param array<string,mixed> $data
     * @return int|null job id, or null when suppressed as a duplicate/disabled
     */
    public function enqueue(
        string $eventKey,
        string $toEmail,
        string $toName,
        array $data = [],
        ?string $dedupeKey = null,
        ?string $subjectOverride = null
    ): ?int {
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            $this->logger->warning('Skipped email with invalid recipient', ['event' => $eventKey]);
            return null;
        }

        $rendered = $this->templates->render($eventKey, $data);

        if (!$rendered['enabled']) {
            $this->logger->info('Email template disabled; not queued', ['event' => $eventKey]);
            return null;
        }

        $job = [
            'event_key'          => $eventKey,
            'dedupe_key'         => $dedupeKey,
            'recipient_email'    => $toEmail,
            'recipient_name'     => $toName !== '' ? $toName : null,
            'subject'            => $subjectOverride ?? $rendered['subject'],
            'template_data_json' => json_encode($this->stripBodies($data), JSON_UNESCAPED_SLASHES) ?: '{}',
            'status'             => 'queued',
            'attempts'           => 0,
            'available_at'       => gmdate('Y-m-d H:i:s'),
        ];

        if ($dedupeKey !== null) {
            $inserted = $this->db->insertIgnore('email_jobs', $job);
            if (!$inserted) {
                $this->logger->info('Duplicate email job suppressed', ['event' => $eventKey, 'dedupe' => $dedupeKey]);
                return null;
            }
            $this->scheduleDelivery();
            return (int) $this->db->scalar('SELECT id FROM email_jobs WHERE dedupe_key = :k', ['k' => $dedupeKey]);
        }

        $id = $this->db->insert('email_jobs', $job);

        // With the queue switched off the message is sent right away. From the
        // command line that is done here. On the website it is done after the
        // page has been delivered (see scheduleDelivery), so a slow mail server
        // can never make a visitor wait.
        if (!$this->queueEnabled() && PHP_SAPI === 'cli') {
            $this->processJob($id);
        } else {
            $this->scheduleDelivery();
        }

        return $id;
    }

    /**
     * Queue a fully pre-rendered message (no template lookup).
     *
     * Used for the administrator's SMTP test. The bodies are stored with the
     * job so the worker sends exactly what was composed, and the message is
     * marked with its own event key for the queue view.
     *
     * @return int|null job id, or null when the recipient was rejected
     */
    public function enqueueRaw(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $textBody,
        string $eventKey = 'admin.test_email'
    ): ?int {
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            $this->logger->warning('Skipped test email with invalid recipient');
            return null;
        }

        return $this->db->insert('email_jobs', [
            'event_key'          => $eventKey,
            'dedupe_key'         => null,
            'recipient_email'    => $toEmail,
            'recipient_name'     => $toName !== '' ? $toName : null,
            'subject'            => $subject,
            'template_data_json' => json_encode([
                'heading'    => $subject,
                'body'       => $htmlBody,
                'body_text'  => $textBody,
                'raw'        => true,
            ], JSON_UNESCAPED_SLASHES) ?: '{}',
            'status'             => 'queued',
            'attempts'           => 0,
            'available_at'       => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Deliver a job immediately and report whether it went out.
     *
     * Used when the queue worker is not running (small deployments) and by the
     * admin test button.
     */
    public function processJobNow(int $jobId): bool
    {
        return $this->processJob($jobId);
    }

    /**
     * Send queued mail right after this web request has been answered, so
     * receipts and notices go out even when no cron job runs `mail:work`.
     *
     * This runs inside a web worker, so it is deliberately cautious:
     *  - the visitor's page is finished first (when the server lets us);
     *  - only one such sender runs at a time (a lock file), so a burst of
     *    visitors cannot pile up SMTP connections;
     *  - it gives up for 90 seconds after a failure, so a down mail server is
     *    not retried by every page view;
     *  - it handles at most 3 emails, stops at the first failure and keeps to
     *    a short time budget, and SMTP itself has a connect/reply timeout.
     *
     * Jobs are claimed atomically, so this and a cron worker can never send
     * the same email twice. A cron worker is still recommended for retries.
     */
    private function scheduleDelivery(): void
    {
        if ($this->drainScheduled || PHP_SAPI === 'cli') {
            return;
        }
        $this->drainScheduled = true;

        register_shutdown_function(function (): void {
            $lock = null;
            $started = microtime(true);
            try {
                // Let the visitor's browser have the finished page first.
                $detached = false;
                if (function_exists('fastcgi_finish_request')) {
                    $detached = (bool) @fastcgi_finish_request();
                } elseif (function_exists('litespeed_finish_request')) {
                    @litespeed_finish_request();
                    $detached = true;
                }

                // Back off while the mail server is failing.
                $state = $this->stateFile('mail-backoff');
                if (is_file($state) && (time() - (int) @filemtime($state)) < 90) {
                    return;
                }

                // One background sender at a time.
                $lock = @fopen($this->stateFile('mail-drain.lock'), 'c');
                if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
                    return;
                }

                ignore_user_abort(true);
                @set_time_limit(40);

                // Do not hold the visitor's session lock while talking to SMTP.
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_write_close();
                }

                $this->recoverStuck(15);

                // If the server could not detach us from the visitor's page the
                // visitor is still waiting, so do much less.
                $stats = $detached
                    ? $this->work(3, $started + 20.0, true)
                    : $this->work(1, $started + 8.0, true);

                if ($stats['failed'] > 0) {
                    @touch($state);
                } elseif ($stats['sent'] > 0 && is_file($state)) {
                    @unlink($state);
                }

                if ($stats['sent'] + $stats['failed'] > 0) {
                    $this->logger->info('Background mail delivery', $stats + [
                        'seconds'  => round(microtime(true) - $started, 1),
                        'detached' => $detached,
                    ]);
                }
            } catch (Throwable $e) {
                error_log('[almarah] background mail delivery failed: ' . $e->getMessage());
            } finally {
                if (is_resource($lock)) {
                    @flock($lock, LOCK_UN);
                    @fclose($lock);
                }
            }
        });
    }

    /** A small file for coordination between requests, in storage/cache. */
    private function stateFile(string $name): string
    {
        $dir = rtrim(app()->basePath(), '/\\') . '/storage/cache';
        if (!is_dir($dir) || !is_writable($dir)) {
            $dir = sys_get_temp_dir();
        }
        return $dir . '/' . $name;
    }

    public function queueEnabled(): bool
    {
        return (bool) ($this->config['queue']['enabled'] ?? true);
    }

    /** @return array<int,array<string,mixed>> */
    public function claimBatch(int $limit): array
    {
        $limit = max(1, min(200, $limit));

        $jobs = $this->db->select(
            "SELECT * FROM email_jobs
             WHERE status = 'queued' AND available_at <= UTC_TIMESTAMP()
             ORDER BY id ASC LIMIT {$limit}"
        );

        $claimed = [];
        foreach ($jobs as $job) {
            // Atomic claim: only one worker can flip queued -> sending.
            $affected = $this->db->run(
                "UPDATE email_jobs SET status = 'sending', attempts = attempts + 1, updated_at = UTC_TIMESTAMP()
                 WHERE id = :id AND status = 'queued'",
                ['id' => $job['id']]
            )->rowCount();

            if ($affected === 1) {
                $job['attempts'] = (int) $job['attempts'] + 1;
                $claimed[] = $job;
            }
        }

        return $claimed;
    }

    /** @return array{sent:int, failed:int, skipped:int} */
    public function work(int $limit = 25, ?float $deadline = null, bool $stopOnFailure = false): array
    {
        $stats = ['sent' => 0, 'failed' => 0, 'skipped' => 0];

        $jobs = $this->claimBatch($limit);
        foreach ($jobs as $i => $job) {
            // Out of time, or the mail server just failed: hand the rest back
            // to the queue untouched for the next attempt (or the cron worker).
            if (($deadline !== null && microtime(true) >= $deadline) || ($stopOnFailure && $stats['failed'] > 0)) {
                $this->release(array_slice($jobs, $i));
                break;
            }

            $result = $this->deliver($job);
            $stats[$result]++;
        }

        return $stats;
    }

    /**
     * Put claimed-but-unsent jobs back in the queue without counting an attempt.
     *
     * @param array<int,array<string,mixed>> $jobs
     */
    private function release(array $jobs): void
    {
        foreach ($jobs as $job) {
            $this->db->run(
                "UPDATE email_jobs SET status = 'queued', attempts = GREATEST(attempts - 1, 0), updated_at = UTC_TIMESTAMP()
                 WHERE id = :id AND status = 'sending'",
                ['id' => $job['id']]
            );
        }
    }

    /** @param array<string,mixed> $job */
    public function processJob(int $jobId): bool
    {
        $job = $this->db->selectOne('SELECT * FROM email_jobs WHERE id = :id', ['id' => $jobId]);
        if ($job === null || $job['status'] === 'sent') {
            return false;
        }

        $this->db->run(
            "UPDATE email_jobs SET status = 'sending', attempts = attempts + 1, updated_at = UTC_TIMESTAMP()
             WHERE id = :id AND status IN ('queued','failed')",
            ['id' => $jobId]
        );

        $job['attempts'] = (int) $job['attempts'] + 1;

        return $this->deliver($job) === 'sent';
    }

    /** @param array<string,mixed> $job @return 'sent'|'failed'|'skipped' */
    private function deliver(array $job): string
    {
        $eventKey = (string) $job['event_key'];
        $data = json_decode((string) ($job['template_data_json'] ?? '{}'), true);
        $data = is_array($data) ? $data : [];

        try {
            // Pre-rendered jobs (admin tests) carry their own bodies.
            if (!empty($data['raw'])) {
                $html = (string) ($data['body'] ?? '');
                $text = (string) ($data['body_text'] ?? '');

                $this->transport->send(
                    (string) $job['recipient_email'],
                    (string) ($job['recipient_name'] ?? ''),
                    (string) $job['subject'],
                    $html,
                    $text
                );

                $this->db->run(
                    "UPDATE email_jobs SET status = 'sent', sent_at = UTC_TIMESTAMP(), last_error = NULL, updated_at = UTC_TIMESTAMP() WHERE id = :id",
                    ['id' => $job['id']]
                );

                $this->logger->info('Pre-rendered email sent', ['job' => (int) $job['id']]);

                return 'sent';
            }

            $rendered = $this->templates->render($eventKey, $data);

            if (!$rendered['enabled']) {
                $this->markSkipped((int) $job['id'], 'Template disabled');
                return 'skipped';
            }

            $this->transport->send(
                (string) $job['recipient_email'],
                (string) ($job['recipient_name'] ?? ''),
                (string) ($job['subject'] !== '' ? $job['subject'] : $rendered['subject']),
                $rendered['html'],
                $rendered['text']
            );

            $this->db->run(
                "UPDATE email_jobs
                 SET status = 'sent', sent_at = UTC_TIMESTAMP(), last_error = NULL, updated_at = UTC_TIMESTAMP()
                 WHERE id = :id",
                ['id' => $job['id']]
            );

            $this->logger->info('Email sent', [
                'event'    => $eventKey,
                'job'      => (int) $job['id'],
                'attempts' => (int) $job['attempts'],
            ]);

            return 'sent';
        } catch (Throwable $e) {
            $this->markFailed($job, $this->safeMessage($e));
            return 'failed';
        }
    }

    private function markSkipped(int $jobId, string $reason): void
    {
        $this->db->run(
            "UPDATE email_jobs SET status = 'cancelled', last_error = :reason, updated_at = UTC_TIMESTAMP() WHERE id = :id",
            ['id' => $jobId, 'reason' => mb_substr($reason, 0, 500)]
        );
    }

    /** @param array<string,mixed> $job */
    private function markFailed(array $job, string $error): void
    {
        $attempts = (int) $job['attempts'];
        $maxRetries = (int) ($this->config['queue']['max_retries'] ?? 3);

        if ($attempts >= $maxRetries) {
            $this->db->run(
                "UPDATE email_jobs SET status = 'failed', last_error = :err, updated_at = UTC_TIMESTAMP() WHERE id = :id",
                ['id' => $job['id'], 'err' => mb_substr($error, 0, 500)]
            );
            $this->logger->error('Email permanently failed', [
                'event'    => (string) $job['event_key'],
                'job'      => (int) $job['id'],
                'attempts' => $attempts,
            ]);
            return;
        }

        $backoff = $this->config['queue']['backoff_seconds'][$attempts - 1] ?? 900;

        $this->db->run(
            "UPDATE email_jobs
             SET status = 'queued', last_error = :err, available_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL :backoff SECOND),
                 updated_at = UTC_TIMESTAMP()
             WHERE id = :id",
            ['id' => $job['id'], 'err' => mb_substr($error, 0, 500), 'backoff' => $backoff]
        );

        $this->logger->warning('Email send failed; queued for retry', [
            'event'    => (string) $job['event_key'],
            'job'      => (int) $job['id'],
            'attempts' => $attempts,
            'backoff'  => $backoff,
        ]);
    }

    private function safeMessage(Throwable $e): string
    {
        $message = $e->getMessage();
        $message = preg_replace('/\b\d{13,19}\b/', '[card-like]', $message) ?? $message;
        $message = preg_replace('/(password[=:\s]+)[^\s,;]+/i', '$1[REDACTED]', $message) ?? $message;
        return mb_substr($message, 0, 500);
    }

    /** Never persist rendered bodies inside the job payload. */
    private function stripBodies(array $data): array
    {
        unset($data['html'], $data['text'], $data['html_body'], $data['text_body'], $data['_token']);
        return $data;
    }

    /** @return array<string,int> */
    public function stats(): array
    {
        $rows = $this->db->select('SELECT status, COUNT(*) AS total FROM email_jobs GROUP BY status');
        $out = ['queued' => 0, 'sending' => 0, 'sent' => 0, 'failed' => 0, 'cancelled' => 0];
        foreach ($rows as $row) {
            $out[(string) $row['status']] = (int) $row['total'];
        }
        return $out;
    }

    /** Requeue jobs stuck in "sending" after a crash. */
    public function recoverStuck(int $olderThanMinutes = 15): int
    {
        return $this->db->run(
            "UPDATE email_jobs SET status = 'queued', updated_at = UTC_TIMESTAMP()
             WHERE status = 'sending' AND updated_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL :mins MINUTE)",
            ['mins' => $olderThanMinutes]
        )->rowCount();
    }

    /** @return array<int,array<string,mixed>> */
    public function recent(int $limit = 50): array
    {
        return $this->db->select(
            'SELECT id, event_key, recipient_email, subject, status, attempts, sent_at, last_error, created_at
             FROM email_jobs ORDER BY id DESC LIMIT ' . (int) $limit
        );
    }
}
