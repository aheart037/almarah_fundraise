<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Immutable-ish request wrapper. Server-side validation always reads input
 * through here so that nothing is trusted straight from a superglobal.
 */
final class Request
{
    /** @var array<string,mixed> */
    private array $query;
    /** @var array<string,mixed> */
    private array $body;
    /** @var array<string,mixed> */
    private array $files;
    /** @var array<string,string> */
    private array $headers;
    /** @var array<string,string> */
    private array $routeParams = [];

    private string $method;
    private string $path;
    private string $ip;
    private string $userAgent;
    /** @var array<string,mixed> */
    private array $attributes = [];

    public function __construct(
        ?string $method = null,
        ?string $path = null,
        ?array $query = null,
        ?array $body = null,
        ?array $files = null,
        ?array $headers = null
    ) {
        $this->method  = strtoupper($method ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $this->query   = $query   ?? $_GET;
        $this->body    = $body    ?? $_POST;
        $this->files   = $files   ?? $_FILES;
        $this->headers = $headers ?? self::collectHeaders();
        $this->path    = $this->normalisePath($path ?? ($_SERVER['REQUEST_URI'] ?? '/'));
        $this->ip      = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->userAgent = substr((string) ($this->headers['user-agent'] ?? ''), 0, 255);
    }

    private static function collectHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = (string) $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $headers[strtolower(str_replace('_', '-', $key))] = (string) $value;
            }
        }
        return $headers;
    }

    private function normalisePath(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = '/' . trim(rawurldecode($path), '/');
        $path = $path === '/' ? '/' : rtrim($path, '/');

        // On a folder install Apache hands us /donate/... while every route is
        // written without the folder, so the prefix comes off here and nowhere
        // else. The rest of the application only ever sees /...
        return BasePath::strip($path);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function path(): string
    {
        return $this->path;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function isAjax(): bool
    {
        return strtolower((string) $this->header('x-requested-with', '')) === 'xmlhttprequest';
    }

    public function wantsJson(): bool
    {
        return $this->isAjax()
            || str_contains(strtolower((string) $this->header('accept', '')), 'application/json');
    }

    public function ip(): string
    {
        return $this->ip;
    }

    public function userAgent(): string
    {
        return $this->userAgent;
    }

    /** Raw input (query + body merged). Never used directly for validation. */
    public function input(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->body)) {
            return is_string($this->body[$key]) ? trim($this->body[$key]) : $this->body[$key];
        }
        if (array_key_exists($key, $this->query)) {
            return is_string($this->query[$key]) ? trim($this->query[$key]) : $this->query[$key];
        }
        return $default;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);
        if (is_array($value)) {
            return $default;
        }
        return trim((string) $value);
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->input($key);
        return $value === null || $value === '' ? $default : (int) $value;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->input($key);

        if ($value === null) {
            return $default;
        }

        return in_array($value, ['1', 1, true, 'true', 'on', 'yes', 'Y'], true);
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    /** @return array<string,mixed> */
    public function only(array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $this->input($key);
        }
        return $out;
    }

    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;
        if (!is_array($file) || !isset($file['tmp_name'])) {
            return null;
        }
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return $file;
    }

    /** @return array<string,mixed> */
    public function json(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function rawBody(): string
    {
        return file_get_contents('php://input') ?: '';
    }

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function routeParam(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function fullUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        // $this->path has the folder prefix removed, so add it back for a URL
        // a browser can follow.
        return $scheme . '://' . $host . BasePath::prefix($this->path);
    }

    /** True when the request arrived over HTTPS (or a proxy says so). */
    public function isSecure(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }
        return strtolower((string) $this->header('x-forwarded-proto', '')) === 'https';
    }
}
