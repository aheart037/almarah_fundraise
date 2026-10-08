<?php

declare(strict_types=1);

namespace App\Payments\Http;

/**
 * Normalised HTTP response for gateway calls.
 */
final class HttpResult
{
    /** @param array<string,string> $headers */
    public function __construct(
        public readonly int $statusCode,
        public readonly string $body,
        public readonly array $headers = [],
        public readonly ?string $error = null,
        public readonly ?string $effectiveUrl = null
    ) {
    }

    public function failed(): bool
    {
        return $this->error !== null || $this->statusCode === 0;
    }

    public function successful(): bool
    {
        return !$this->failed() && $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /** @return array<string,mixed> */
    public function json(): array
    {
        $decoded = json_decode($this->body, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function isJson(): bool
    {
        if (trim($this->body) === '') {
            return false;
        }
        json_decode($this->body);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /** A short, credential-free description suitable for logs. */
    public function describe(): string
    {
        return sprintf('HTTP %d%s', $this->statusCode, $this->error !== null ? ' (' . $this->error . ')' : '');
    }
}
