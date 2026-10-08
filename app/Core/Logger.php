<?php

declare(strict_types=1);

namespace App\Core;

/**
 * File logger with secret redaction. Payment credentials, tokens and
 * credential-bearing query strings never reach disk.
 */
final class Logger
{
    /** @var array<int,string> */
    private array $redactKeys = [
        'password', 'passwd', 'pwd', 'username', 'userName', 'UserName',
        'secret', 'api_secret', 'apiSecret', 'token', '_token', 'csrf',
        'authorization', 'card', 'cardnumber', 'cvv', 'cvc', 'expiry',
        'smtp_password', 'db_password', 'customer', 'Customer',
    ];

    /** @var array<int,string> */
    private array $redactQueryParams = [
        'password', 'userName', 'username', 'Customer', 'customer', 'Terminal',
    ];

    public function __construct(private string $directory, private string $channel = 'app')
    {
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0775, true);
        }
    }

    /** @param array<string,mixed> $context */
    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public function warning(string $message, array $context = []): void
    {
        $this->write('WARNING', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public function security(string $message, array $context = []): void
    {
        $this->write('SECURITY', $message, $context, 'security');
    }

    /** @param array<string,mixed> $context */
    public function payment(string $message, array $context = []): void
    {
        $this->write('PAYMENT', $message, $context, 'payments');
    }

    private function write(string $level, string $message, array $context, ?string $channel = null): void
    {
        $channel = $channel ?? $this->channel;
        $line = sprintf(
            "[%s] %s.%s: %s %s\n",
            gmdate('Y-m-d H:i:s') . ' UTC',
            $channel,
            $level,
            $this->sanitiseString($message),
            $context === [] ? '' : json_encode($this->redact($context), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        $file = rtrim($this->directory, '/') . '/' . $channel . '-' . gmdate('Y-m-d') . '.log';
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    /** Recursively remove sensitive keys and scrub credential-bearing URLs. */
    public function redact(array $context, int $depth = 0): array
    {
        if ($depth > 6) {
            return ['_truncated' => true];
        }

        $out = [];
        foreach ($context as $key => $value) {
            if (is_string($key) && $this->isSensitive($key)) {
                $out[$key] = '[REDACTED]';
                continue;
            }
            if (is_array($value)) {
                $out[$key] = $this->redact($value, $depth + 1);
            } elseif (is_string($value)) {
                $out[$key] = $this->sanitiseString($value);
            } elseif (is_object($value)) {
                $out[$key] = '[object ' . get_class($value) . ']';
            } else {
                $out[$key] = $value;
            }
        }
        return $out;
    }

    private function isSensitive(string $key): bool
    {
        $lower = strtolower($key);
        foreach ($this->redactKeys as $needle) {
            if (str_contains($lower, strtolower($needle))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Strip credential-bearing query strings and any accidental card data.
     * Meezan's legacy API transmits credentials as GET parameters, so a raw
     * request URL must never be logged.
     */
    public function sanitiseString(string $value): string
    {
        if (str_contains($value, '?')) {
            $value = preg_replace_callback(
                '#([?&])([^=&\s]+)=([^&\s]*)#',
                function (array $m): string {
                    $name = $m[2];
                    foreach ($this->redactQueryParams as $needle) {
                        if (strcasecmp($name, $needle) === 0) {
                            return $m[1] . $name . '=[REDACTED]';
                        }
                    }
                    return $m[1] . $name . '=' . $m[3];
                },
                $value
            ) ?? $value;
        }

        // Long digit runs are never written to logs.
        return preg_replace('/\b\d{13,19}\b/', '[CARD-LIKE-REDACTED]', $value) ?? $value;
    }

    public function path(): string
    {
        return $this->directory;
    }
}
