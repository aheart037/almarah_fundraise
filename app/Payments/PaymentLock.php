<?php

declare(strict_types=1);

namespace App\Payments;

use App\Core\Database;

/**
 * Short-lived advisory lock backed by the payment_locks table.
 *
 * Used so that two concurrent callbacks (or a callback racing an admin
 * reconcile) cannot finalize the same transaction twice, and so a refund
 * cannot start while a finalization is in flight.
 */
final class PaymentLock
{
    public function __construct(private Database $db, private int $defaultTimeoutSeconds = 45)
    {
    }

    /**
     * Attempt to acquire a lock. Returns an owner token on success, null when
     * another process holds it.
     */
    public function acquire(string $key, ?int $timeoutSeconds = null): ?string
    {
        $timeout = $timeoutSeconds ?? $this->defaultTimeoutSeconds;
        $owner = bin2hex(random_bytes(16));

        // Clear any expired lock first so a crashed worker cannot deadlock us.
        $this->db->run('DELETE FROM payment_locks WHERE lock_key = :k AND expires_at < UTC_TIMESTAMP()', ['k' => $key]);

        $inserted = $this->db->insertIgnore('payment_locks', [
            'lock_key'    => $key,
            'owner_token' => $owner,
            'expires_at'  => gmdate('Y-m-d H:i:s', time() + $timeout),
        ]);

        return $inserted ? $owner : null;
    }

    public function release(string $key, string $owner): void
    {
        $this->db->run(
            'DELETE FROM payment_locks WHERE lock_key = :k AND owner_token = :o',
            ['k' => $key, 'o' => $owner]
        );
    }

    /**
     * Run a callback while holding a lock. When the lock cannot be acquired the
     * callback is skipped and null is returned so the caller can respond with
     * "already being processed" instead of racing.
     */
    public function withLock(string $key, callable $callback, ?int $timeoutSeconds = null): mixed
    {
        $owner = $this->acquire($key, $timeoutSeconds);

        if ($owner === null) {
            return null;
        }

        try {
            return $callback();
        } finally {
            $this->release($key, $owner);
        }
    }

    public function purgeExpired(): int
    {
        return $this->db->run('DELETE FROM payment_locks WHERE expires_at < UTC_TIMESTAMP()')->rowCount();
    }
}
