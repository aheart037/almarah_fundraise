<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Response value object. Headers are always explicit; no response may be
 * emitted with a missing Content-Type.
 */
final class Response
{
    private int $status;
    private string $body;
    /** @var array<string,string> */
    private array $headers;

    public function __construct(string $body = '', int $status = 200, array $headers = [])
    {
        $this->body = $body;
        $this->status = $status;
        $this->headers = $headers;
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /** @param array<string,mixed> $data */
    public static function json(array $data, int $status = 200): self
    {
        return new self(
            json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
            $status,
            ['Content-Type' => 'application/json; charset=utf-8']
        );
    }

    public static function text(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    /**
     * Redirect. The Location target is always generated internally by the
     * application (never echoed from user input) to prevent open redirects.
     */
    public static function redirect(string $to, int $status = 302): self
    {
        // Controllers redirect with site-relative paths ('/admin'). On a folder
        // install those must keep the folder prefix, or the browser lands
        // outside the application. Absolute URLs are left alone.
        return new self('', $status, ['Location' => self::locationFor($to)]);
    }

    public static function noContent(): self
    {
        return new self('', 204);
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /** @param array<string,string> $headers */
    public function withHeaders(array $headers): self
    {
        foreach ($headers as $name => $value) {
            $this->headers[$name] = $value;
        }
        return $this;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    /** @return array<string,string> */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Where a redirect actually points. Site-relative targets gain the folder
     * the site is served from (https://host/donate), absolute URLs are left
     * alone, and a target that already carries the folder is not doubled.
     */
    public static function locationFor(string $target): string
    {
        return BasePath::prefix($target);
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                // Middleware redirects (guest -> /login, unverified -> /verify-email)
                // are raised as exceptions rather than built by redirect(), so
                // the folder prefix is applied here as the last line of defence:
                // on a subfolder install every redirect keeps the folder, no
                // matter which code path produced it. prefix() ignores absolute
                // URLs and values that already carry the folder.
                if (strcasecmp($name, 'Location') === 0) {
                    $value = self::locationFor($value);
                }

                header($name . ': ' . $value, true);
            }
            // Security headers applied to every response.
            header('X-Content-Type-Options: nosniff');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            header('X-Frame-Options: SAMEORIGIN');
        }

        echo $this->body;
    }
}
