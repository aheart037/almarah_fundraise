<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal .env loader. Values are never written back to disk and never logged.
 */
final class Env
{
    /** @var array<string,string> */
    private static array $values = [];

    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

        if (!is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // strip matching surrounding quotes
            if (strlen($value) >= 2) {
                $first = $value[0];
                $last = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            if ($key === '') {
                continue;
            }

            self::$values[$key] = $value;

            // Do not clobber real environment variables that are already set
            // (container/orchestrator secrets take precedence over the file).
            if (getenv($key) === false) {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $raw = getenv($key);
        if ($raw === false) {
            $raw = self::$values[$key] ?? null;
        }
        if ($raw === null || $raw === '') {
            return $default;
        }

        return match (strtolower((string) $raw)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            'empty', '(empty)' => '',
            default            => $raw,
        };
    }

    public static function bool(string $key, bool $default = false): bool
    {
        return (bool) self::get($key, $default);
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key);
        return $value === null ? $default : (int) $value;
    }

    /**
     * A credential value that must never be echoed back to a browser.
     * Returns '' when unset so callers can treat it as "not configured".
     */
    public static function secret(string $key): string
    {
        $value = self::get($key, '');
        return is_string($value) ? $value : '';
    }

    public static function has(string $key): bool
    {
        $value = self::get($key);
        return $value !== null && $value !== '';
    }

    /** Test-only helper (phpunit bootstrap). */
    public static function setForTesting(string $key, string $value): void
    {
        self::$values[$key] = $value;
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}
