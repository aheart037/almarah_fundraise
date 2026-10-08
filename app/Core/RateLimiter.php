<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Database-backed rate limiter used for login throttling, donation spam
 * protection and callback flood protection.
 */
final class RateLimiter
{
    public function __construct(private Database $db)
    {
    }

    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        $row = $this->db->selectOne(
            'SELECT attempts, expires_at FROM rate_limits WHERE `key` = :k LIMIT 1',
            ['k' => $this->hashKey($key)]
        );

        if ($row === null) {
            return false;
        }

        if (strtotime((string) $row['expires_at'] . ' UTC') < time()) {
            $this->clear($key);
            return false;
        }

        return (int) $row['attempts'] >= $maxAttempts;
    }

    public function hit(string $key, int $decayMinutes): void
    {
        $hashed = $this->hashKey($key);
        $now = gmdate('Y-m-d H:i:s');

        $this->db->run(
            'INSERT INTO rate_limits (`key`, attempts, expires_at, created_at, updated_at)
             VALUES (:k, 1, DATE_ADD(UTC_TIMESTAMP(), INTERVAL :mins MINUTE), :now, :now2)
             ON DUPLICATE KEY UPDATE attempts = attempts + 1, updated_at = :now3',
            ['k' => $hashed, 'mins' => $decayMinutes, 'now' => $now, 'now2' => $now, 'now3' => $now]
        );
    }

    public function attempts(string $key): int
    {
        $value = $this->db->scalar(
            'SELECT attempts FROM rate_limits WHERE `key` = :k',
            ['k' => $this->hashKey($key)]
        );
        return (int) ($value ?? 0);
    }

    public function clear(string $key): void
    {
        $this->db->run('DELETE FROM rate_limits WHERE `key` = :k', ['k' => $this->hashKey($key)]);
    }

    public function availableIn(string $key): int
    {
        $row = $this->db->selectOne(
            'SELECT expires_at FROM rate_limits WHERE `key` = :k',
            ['k' => $this->hashKey($key)]
        );
        if ($row === null) {
            return 0;
        }
        return max(0, strtotime((string) $row['expires_at'] . ' UTC') - time());
    }

    /** Keys are hashed so that emails/IPs are not stored in plain text. */
    private function hashKey(string $key): string
    {
        return hash('sha256', $key);
    }

    public function purgeExpired(): int
    {
        return $this->db->run('DELETE FROM rate_limits WHERE expires_at < UTC_TIMESTAMP()')->rowCount();
    }
}
