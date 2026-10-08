<?php

declare(strict_types=1);

namespace App\Core;

/**
 * CSRF protection. Tokens are per-session, compared with hash_equals, and
 * accepted from either a POST field or an X-CSRF-Token header.
 */
final class Csrf
{
    private const KEY = '_csrf_token';

    public function __construct(private Session $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get(self::KEY);
        if (!is_string($token) || strlen($token) < 32) {
            $token = bin2hex(random_bytes(32));
            $this->session->set(self::KEY, $token);
        }
        return $token;
    }

    public function field(): string
    {
        return '<input type="hidden" name="_token" value="' . htmlspecialchars($this->token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public function verify(?string $token): bool
    {
        $expected = $this->session->get(self::KEY);
        if (!is_string($expected) || $expected === '') {
            return false;
        }
        if (!is_string($token) || $token === '') {
            return false;
        }
        return hash_equals($expected, $token);
    }

    public function check(Request $request): bool
    {
        $token = $request->input('_token') ?? $request->header('x-csrf-token');
        return $this->verify(is_string($token) ? $token : null);
    }
}
