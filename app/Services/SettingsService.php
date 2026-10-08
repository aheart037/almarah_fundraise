<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Crypto;
use App\Core\Database;
use App\Core\Logger;
use Throwable;

/**
 * Database-backed settings, including gateway and SMTP configuration.
 *
 * Precedence: database value  >  environment value  >  config default.
 * Secret values are encrypted at rest with APP_KEY and are never returned to
 * a browser after being saved (the UI only shows whether a value is set).
 */
final class SettingsService
{
    /** @var array<string,string|null> */
    private array $cache = [];

    private ?Crypto $crypto = null;

    public function __construct(
        private Database $db,
        private Logger $logger,
        private array $gatewayDefaults = []
    ) {
    }

    private function crypto(): ?Crypto
    {
        if ($this->crypto instanceof Crypto) {
            return $this->crypto;
        }
        try {
            $this->crypto = Crypto::fromAppKey();
        } catch (Throwable $e) {
            $this->logger->warning('APP_KEY missing; encrypted settings unavailable', ['reason' => $e->getMessage()]);
            $this->crypto = null;
        }
        return $this->crypto;
    }

    /** Raw stored value (still encrypted for secrets) or null. */
    public function raw(string $key): ?string
    {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        try {
            $value = $this->db->scalar('SELECT setting_value FROM site_settings WHERE setting_key = :k', ['k' => $key]);
        } catch (Throwable $e) {
            // During first install the table may not exist yet.
            $value = null;
        }

        $this->cache[$key] = $value === null ? null : (string) $value;
        return $this->cache[$key];
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $raw = $this->raw($key);
        if ($raw === null || $raw === '') {
            return $default;
        }

        $crypto = $this->crypto();
        if ($crypto !== null && $crypto->isEncrypted($raw)) {
            try {
                return $crypto->decrypt($raw);
            } catch (Throwable $e) {
                $this->logger->error('Failed to decrypt setting', ['key' => $key]);
                return $default;
            }
        }

        return $raw;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key);
        if ($value === null || $value === '') {
            return $default;
        }
        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->get($key);
        return $value === null || $value === '' ? $default : (int) $value;
    }

    /** True when a secret has been stored (used by the admin UI). */
    public function hasSecret(string $key): bool
    {
        $raw = $this->raw($key);
        return $raw !== null && $raw !== '';
    }

    public function set(string $key, ?string $value, bool $encrypted = false, ?int $userId = null): void
    {
        unset($this->cache[$key]);

        $stored = $value;
        if ($encrypted && $value !== null && $value !== '') {
            $crypto = $this->crypto();
            if ($crypto === null) {
                throw new \RuntimeException('Cannot store an encrypted setting without APP_KEY.');
            }
            $stored = $crypto->encrypt($value);
        }

        $existing = $this->db->scalar('SELECT id FROM site_settings WHERE setting_key = :k', ['k' => $key]);

        if ($existing) {
            $this->db->update('site_settings', [
                'setting_value' => $stored,
                'is_encrypted'  => $encrypted ? 1 : 0,
                'updated_by'    => $userId,
            ], 'setting_key = :k', ['k' => $key]);
        } else {
            $this->db->insert('site_settings', [
                'setting_key'   => $key,
                'setting_value' => $stored,
                'is_encrypted'  => $encrypted ? 1 : 0,
                'updated_by'    => $userId,
            ]);
        }
    }

    public function forget(string $key): void
    {
        unset($this->cache[$key]);
        $this->db->delete('site_settings', 'setting_key = :k', ['k' => $key]);
    }

    /** @param array<string,string|null> $pairs */
    public function setMany(array $pairs, ?int $userId = null, array $encryptedKeys = []): void
    {
        foreach ($pairs as $key => $value) {
            $this->set($key, $value, in_array($key, $encryptedKeys, true), $userId);
        }
    }

    // ---------------------------------------------------------------------
    // Gateway configuration
    // ---------------------------------------------------------------------

    /**
     * Effective configuration for a gateway: environment defaults overlaid
     * with any database overrides an administrator has saved.
     *
     * @return array<string,mixed>
     */
    public function gatewayConfig(string $code): array
    {
        $defaults = $this->gatewayDefaults['gateways'][$code] ?? [];
        $prefix = strtoupper($code);

        $overrides = [
            'enabled'     => $this->dbBool("gateway.{$code}.enabled", (bool) ($defaults['enabled'] ?? false)),
            'environment' => $this->get("gateway.{$code}.environment", (string) ($defaults['environment'] ?? 'sandbox')),
            'timeout'     => $this->int("gateway.{$code}.timeout", (int) ($defaults['timeout'] ?? 30)),
        ];

        $secretKeys = ['username', 'password'];
        foreach (['username', 'password', 'merchant_id', 'customer', 'store', 'terminal', 'currency_code', 'currency_name', 'currency'] as $field) {
            if (!array_key_exists($field, $defaults)) {
                continue;
            }
            $stored = $this->get("gateway.{$code}.{$field}");
            $overrides[$field] = ($stored === null || $stored === '') ? $defaults[$field] : $stored;
        }

        foreach (['sandbox_url', 'live_url'] as $field) {
            $stored = $this->get("gateway.{$code}.{$field}");
            $overrides[$field] = ($stored === null || $stored === '') ? ($defaults[$field] ?? '') : $stored;
        }

        $config = array_merge($defaults, $overrides);

        // Secrets never fall back to a config file default that is empty.
        foreach ($secretKeys as $field) {
            $config[$field] = (string) ($config[$field] ?? '');
        }

        return $config;
    }

    private function dbBool(string $key, bool $default): bool
    {
        $value = $this->get($key);
        if ($value === null || $value === '') {
            return $default;
        }
        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    /** @return array<string,string|null> */
    public function mailOverrides(): array
    {
        $defaults = [
            'host'        => null,
            'port'        => null,
            'encryption'  => null,
            'username'    => null,
            'password'    => null,
            'from_address'=> null,
            'from_name'   => null,
            'reply_to'    => null,
            'verify_peer' => null,
        ];

        return [
            'host'         => $this->get('mail.host'),
            'port'         => $this->get('mail.port'),
            'encryption'   => $this->get('mail.encryption'),
            'username'     => $this->get('mail.username'),
            'password'     => $this->get('mail.password'),
            'from_address' => $this->get('mail.from_address'),
            'from_name'    => $this->get('mail.from_name'),
            'reply_to'     => $this->get('mail.reply_to'),
            'verify_peer'  => $this->get('mail.verify_peer'),
        ] + $defaults;
    }

    /** SMTP is "configured" when we have a host and a from address. */
    public function mailConfigured(): bool
    {
        $config = $this->mailOverrides();
        $host = $config['host'] ?: (string) \App\Core\Config::get('mail.host', '');
        return $host !== '' && $this->fromAddress() !== '';
    }

    public function fromAddress(): string
    {
        $value = $this->get('mail.from_address');
        if ($value !== null && $value !== '') {
            return $value;
        }
        return (string) \App\Core\Config::get('mail.from_address', '');
    }

    public function gatewayEnabled(string $code): bool
    {
        return (bool) ($this->gatewayConfig($code)['enabled'] ?? false);
    }

    /** @return array<string,bool> */
    public function gatewayStatus(): array
    {
        $out = [];
        foreach (array_keys($this->gatewayDefaults['gateways'] ?? []) as $code) {
            $config = $this->gatewayConfig($code);
            $out[$code] = (bool) ($config['enabled'] ?? false)
                && (string) ($config['username'] ?? '') !== ''
                && (string) ($config['password'] ?? '') !== '';
        }
        return $out;
    }
}
