<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Authenticated symmetric encryption for values that must be recoverable
 * (gateway passwords, SMTP password). Passwords for user accounts are never
 * encrypted — they are hashed with password_hash().
 *
 * Ciphertext format:  v1:<base64 nonce>:<base64 tag>:<base64 ciphertext>
 */
final class Crypto
{
    private const CIPHER = 'aes-256-gcm';
    private const PREFIX = 'v1';

    public function __construct(private string $key)
    {
        if ($key === '') {
            throw new RuntimeException('APP_KEY is not set; encrypted settings cannot be used.');
        }
    }

    public static function fromAppKey(): self
    {
        $key = (string) Config::get('app.key', '');

        if ($key === '') {
            $key = (string) Env::get('APP_KEY', '');
        }

        if ($key === '') {
            throw new RuntimeException('APP_KEY is not set.');
        }

        // Accept any string; derive a fixed-length key deterministically.
        return new self(hash('sha256', $key, true));
    }

    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(12);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Encryption failed.');
        }

        return implode(':', [
            self::PREFIX,
            base64_encode($nonce),
            base64_encode($tag),
            base64_encode($ciphertext),
        ]);
    }

    public function decrypt(string $payload): string
    {
        $parts = explode(':', $payload);
        if (count($parts) !== 4 || $parts[0] !== self::PREFIX) {
            throw new RuntimeException('Encrypted value is malformed.');
        }

        [, $nonce, $tag, $ciphertext] = $parts;

        $plaintext = openssl_decrypt(
            base64_decode($ciphertext, true) ?: '',
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            base64_decode($nonce, true) ?: '',
            base64_decode($tag, true) ?: ''
        );

        if ($plaintext === false) {
            throw new RuntimeException('Unable to decrypt value (wrong APP_KEY?).');
        }

        return $plaintext;
    }

    public function isEncrypted(string $value): bool
    {
        return str_starts_with($value, self::PREFIX . ':');
    }

    public static function generateAppKey(): string
    {
        return 'base64:' . base64_encode(random_bytes(32));
    }
}
