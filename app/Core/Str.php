<?php

declare(strict_types=1);

namespace App\Core;

final class Str
{
    public static function slug(string $value, int $maxLength = 80): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? '';
        $value = trim($value, '-');
        $value = preg_replace('/-{2,}/', '-', $value) ?? '';
        if ($value === '') {
            $value = 'item';
        }
        return mb_substr($value, 0, $maxLength);
    }

    /** Cryptographically secure, URL-safe random string for public references. */
    public static function randomToken(int $bytes = 32): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }

    public static function randomHex(int $bytes = 16): string
    {
        return bin2hex(random_bytes($bytes));
    }

    /** Human-friendly donation reference, e.g. ALM-2026-7F3K9Q2B. */
    public static function publicReference(string $prefix = 'ALM'): string
    {
        return sprintf(
            '%s-%s-%s',
            $prefix,
            gmdate('Y'),
            strtoupper(bin2hex(random_bytes(4)))
        );
    }

    public static function limit(string $value, int $limit, string $end = '…'): string
    {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }
        return rtrim(mb_substr($value, 0, $limit)) . $end;
    }

    public static function maskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return '***';
        }
        $name = $parts[0];
        $visible = mb_substr($name, 0, 1);
        return $visible . str_repeat('*', max(1, mb_strlen($name) - 1)) . '@' . $parts[1];
    }

    /** Constant-time comparison that tolerates null input. */
    public static function equals(?string $a, ?string $b): bool
    {
        if ($a === null || $b === null || $a === '' || $b === '') {
            return false;
        }
        return hash_equals($a, $b);
    }

    public static function isHttpsUrl(?string $url): bool
    {
        if (!is_string($url) || $url === '') {
            return false;
        }
        return str_starts_with(strtolower($url), 'https://') && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Returns the lowercase host of a URL, or null when unparseable.
     * Used to allowlist provider payment hosts.
     */
    public static function host(?string $url): ?string
    {
        if (!is_string($url) || $url === '') {
            return null;
        }
        $host = parse_url($url, PHP_URL_HOST);
        return is_string($host) ? strtolower($host) : null;
    }

    /** @return array<string,string> */
    public static function initials(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = mb_substr($parts[0] ?? 'A', 0, 1);
        $second = isset($parts[1]) ? mb_substr($parts[1], 0, 1) : '';
        return ['first' => mb_strtoupper($first), 'second' => mb_strtoupper($second)];
    }
}
