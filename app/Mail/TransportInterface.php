<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Transport abstraction so SMTP can be swapped for a capturing transport in
 * tests without touching the queue or the application code.
 */
interface TransportInterface
{
    /**
     * @return bool true when the message was accepted by the server
     * @throws \RuntimeException on transport failure (message is safe to log)
     */
    public function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $textBody
    ): bool;

    /** Verify credentials/host reachability without sending. */
    public function testConnection(): bool;

    /** Human-readable description of the active configuration (no secrets). */
    public function describe(): string;
}
