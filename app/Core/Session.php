<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Hardened session wrapper: HttpOnly, SameSite, optional Secure, ID rotation,
 * absolute + idle timeouts.
 */
final class Session
{
    private bool $started = false;

    public function __construct(private array $config = [])
    {
    }

    public function start(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            return;
        }

        if (headers_sent()) {
            $this->started = true;
            return;
        }

        $lifetime = (int) ($this->config['lifetime'] ?? 120) * 60;

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => (bool) ($this->config['secure'] ?? false),
            'httponly' => true,
            'samesite' => (string) ($this->config['samesite'] ?? 'Lax'),
        ]);

        session_name((string) ($this->config['name'] ?? 'almarah_session'));
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', (string) $lifetime);
        ini_set('session.use_only_cookies', '1');

        session_start();
        $this->started = true;

        $this->enforceTimeouts($lifetime);
    }

    private function enforceTimeouts(int $lifetime): void
    {
        $now = time();

        if (!isset($_SESSION['_created'])) {
            $_SESSION['_created'] = $now;
        }
        $_SESSION['_last_activity'] = $now;

        $idle = $now - (int) ($_SESSION['_last_activity_at'] ?? $now);
        $age  = $now - (int) $_SESSION['_created'];

        if ($idle > $lifetime || $age > $lifetime * 4) {
            $this->flush();
            session_regenerate_id(true);
            $_SESSION['_created'] = $now;
        }

        $_SESSION['_last_activity_at'] = $now;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Read a value and remove it in one step. Used for one-shot values such as
     * the "intended" URL captured before a sign-in redirect.
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);

        return $value;
    }

    public function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public function flush(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $this->started = false;
    }

    public function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    public function getFlash(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    public function hasFlash(string $key): bool
    {
        return isset($_SESSION['_flash'][$key]);
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        return $_SESSION;
    }
}
