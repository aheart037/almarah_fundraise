<?php

declare(strict_types=1);

namespace App\Core;

/**
 * The simple configuration file.
 *
 * Hosting accounts with limited access should not have to deal with a dozen
 * environment variables. This class reads <app>/config.php — a plain PHP file
 * with a handful of values — and turns it into the same settings the rest of
 * the application already reads, before anything else boots.
 *
 * Order of precedence (highest first):
 *   1. real environment variables (set by the hosting panel, e.g. container env)
 *   2. config.php
 *   3. .env            (kept for advanced setups and backwards compatibility)
 *   4. defaults in config/*.php
 *
 * config.php wins over .env because it is applied first and Env::load() never
 * overwrites a variable that is already present.
 */
final class SimpleConfig
{
    public const FILE = 'config.php';

    /** Simple config key => the environment variable it feeds. */
    private const MAP = [
        'db_host'     => 'DB_HOST',
        'db_name'     => 'DB_DATABASE',
        'db_user'     => 'DB_USERNAME',
        'db_pass'     => 'DB_PASSWORD',
        'site_url'    => 'APP_URL',
        'timezone'    => 'APP_TIMEZONE',
        'public_path' => 'PUBLIC_PATH',
        'setup_lock'  => 'SETUP_LOCK',
    ];

    /** The minimum a working installation needs. */
    private const REQUIRED = ['db_name', 'db_user', 'db_pass'];

    /** @var array<string,string>|null */
    private static ?array $values = null;

    /**
     * Reads config.php. Never throws: a broken or missing file is reported by
     * status() so the setup page can explain what to fix.
     *
     * @return array<string,string>
     */
    public static function values(string $basePath): array
    {
        if (self::$values !== null) {
            return self::$values;
        }

        $file = rtrim($basePath, '/') . '/' . self::FILE;

        if (!is_file($file)) {
            return self::$values = [];
        }

        try {
            /** @var mixed $loaded */
            $loaded = require $file;
        } catch (\Throwable $e) {
            return self::$values = [];
        }

        if (!is_array($loaded)) {
            return self::$values = [];
        }

        $values = [];
        foreach (self::MAP as $key => $envKey) {
            if (!array_key_exists($key, $loaded)) {
                continue;
            }
            $value = $loaded[$key];
            if (is_scalar($value) || $value === null) {
                $values[$key] = trim((string) $value);
            }
        }

        return self::$values = $values;
    }

    /**
     * Applies config.php to the environment. Called from bootstrap/app.php
     * before the container is built.
     */
    public static function apply(string $basePath): void
    {
        $values = self::values($basePath);

        foreach (self::MAP as $key => $envKey) {
            $value = $values[$key] ?? '';

            if ($value === '' || $value === null) {
                continue;
            }

            // An explicit value from the hosting environment always wins.
            if (getenv($envKey) !== false && getenv($envKey) !== '') {
                continue;
            }

            putenv($envKey . '=' . $value);
            $_ENV[$envKey] = $value;
            $_SERVER[$envKey] = $value;
        }

        // A secure session cookie on a plain-HTTP site makes sign-in impossible,
        // so it is derived from the address instead of being another setting to
        // get wrong. (Set SESSION_SECURE_COOKIE in .env to override.)
        if (getenv('SESSION_SECURE_COOKIE') === false) {
            $siteUrl = $values['site_url'] ?? '';
            $secure = str_starts_with(strtolower($siteUrl), 'https://') ? 'true' : 'false';

            putenv('SESSION_SECURE_COOKIE=' . $secure);
            $_ENV['SESSION_SECURE_COOKIE'] = $secure;
        }

        self::ensureAppKey($basePath);
    }

    /**
     * Creates the encryption key on first run if one is not configured.
     *
     * The key encrypts payment-gateway and SMTP credentials stored in the
     * database. Generating it automatically means the hosting account needs no
     * command line at all; it is kept in storage/app/app-key.txt (outside the
     * web root) and never displayed.
     */
    public static function ensureAppKey(string $basePath): void
    {
        if (getenv('APP_KEY') !== false && getenv('APP_KEY') !== '') {
            return;
        }

        $directory = rtrim($basePath, '/') . '/storage/app';
        $file = $directory . '/app-key.txt';

        if (is_file($file)) {
            $existing = trim((string) @file_get_contents($file));
            if (str_starts_with($existing, 'base64:') && strlen($existing) > 40) {
                putenv('APP_KEY=' . $existing);
                $_ENV['APP_KEY'] = $existing;
                return;
            }
        }

        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            // Reported by status(); the application still boots.
            return;
        }

        $key = 'base64:' . base64_encode(random_bytes(32));

        if (@file_put_contents($file, $key . "\n", LOCK_EX) === false) {
            return;
        }

        @chmod($file, 0600);

        putenv('APP_KEY=' . $key);
        $_ENV['APP_KEY'] = $key;
    }

    /**
     * What the setup page needs to know, including what is missing.
     *
     * @return array{
     *   file:string, exists:bool, readable:bool, missing:array<int,string>,
     *   configured:bool, values:array<string,string>, key_file:string, key_present:bool
     * }
     */
    public static function status(string $basePath): array
    {
        $basePath = rtrim($basePath, '/');
        $file = $basePath . '/' . self::FILE;
        $values = self::values($basePath);

        $missing = [];
        foreach (self::REQUIRED as $key) {
            if (($values[$key] ?? '') === '') {
                $missing[] = $key;
            }
        }

        $keyFile = $basePath . '/storage/app/app-key.txt';

        return [
            'file'         => $file,
            'exists'       => is_file($file),
            'readable'     => is_readable($file),
            'missing'      => $missing,
            'configured'   => $missing === [],
            'values'       => $values,
            'key_file'     => $keyFile,
            'key_present'  => is_file($keyFile) || (getenv('APP_KEY') !== false && getenv('APP_KEY') !== ''),
        ];
    }

    /**
     * Forgets the cached file contents.
     *
     * Only the test suite needs this: a real request reads config.php once.
     */
    public static function reset(): void
    {
        self::$values = null;
    }

    /** Human label for a missing field, used by the setup page. */
    public static function label(string $key): string
    {
        return match ($key) {
            'db_name' => "db_name   (database name, e.g. 'almarf1_almarah_platform')",
            'db_user' => "db_user   (database username, e.g. 'almarf1_almarah')",
            'db_pass' => 'db_pass   (database password)',
            default   => $key,
        };
    }
}
