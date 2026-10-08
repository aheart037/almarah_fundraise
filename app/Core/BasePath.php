<?php

declare(strict_types=1);

namespace App\Core;

/**
 * The folder the site is served from, when it is not the domain root.
 *
 * A cPanel account can serve the platform from a subfolder of public_html
 * (https://example.com/donate) instead of the domain root. Apache then hands
 * the application every request prefixed with that folder — /donate/setup
 * rather than /setup — while the router, the links and the redirects inside the
 * application are all written without it. This class owns that difference so
 * nothing else has to know about it:
 *
 *   Request::path()        strips the prefix, so routes match again
 *   Router::url()          adds it, so links point at the right place
 *   Response::redirect()   adds it, so redirects do not fall out of the folder
 *
 * Where the value comes from, in order:
 *   1. the path part of APP_URL (config.php 'site_url') when it has one;
 *   2. otherwise the directory the front controller is being served from
 *      (SCRIPT_NAME), which covers a folder install before site_url is set.
 *
 * The value is always normalised to '' (domain root) or a '/folder' prefix
 * with no trailing slash.
 */
final class BasePath
{
    private static string $path = '';

    private static bool $booted = false;

    /** Compute the prefix for this request. Safe to call more than once. */
    public static function boot(?string $configuredUrl = null): void
    {
        self::$path = self::detect(
            (string) $configuredUrl,
            (string) ($_SERVER['SCRIPT_NAME'] ?? ''),
            PHP_SAPI === 'cli',
            (string) ($_SERVER['REQUEST_URI'] ?? '')
        );
        self::$booted = true;
    }

    /** '' at the domain root, or '/donate' for a folder install. */
    public static function get(): string
    {
        return self::$path;
    }

    public static function isRoot(): bool
    {
        return self::$path === '';
    }

    /**
     * Turn a site-relative path into one the browser can use:
     * '/admin' becomes '/donate/admin' on a folder install, and is returned
     * unchanged at the domain root. Absolute URLs (http://, https://, //host)
     * and values that are already prefixed pass through untouched.
     */
    public static function prefix(string $path): string
    {
        if ($path === '' || self::$path === '') {
            return $path;
        }

        if (preg_match('#^(?:[a-z][a-z0-9+.-]*:|//)#i', $path)) {
            return $path; // absolute URL or protocol-relative: not ours to rewrite
        }

        if ($path[0] !== '/') {
            return $path; // query string, fragment or deliberately relative
        }

        if ($path === self::$path || str_starts_with($path, self::$path . '/')) {
            return $path; // already prefixed by a caller that knew
        }

        return self::$path . $path;
    }

    /**
     * Remove the prefix from an incoming request path so the router sees the
     * same paths on a folder install as it does at the domain root.
     */
    public static function strip(string $path): string
    {
        if (self::$path === '') {
            return $path;
        }

        if ($path === self::$path) {
            return '/';
        }

        if (str_starts_with($path, self::$path . '/')) {
            $stripped = substr($path, strlen(self::$path));

            return $stripped === '' || $stripped === false ? '/' : $stripped;
        }

        return $path;
    }

    /**
     * Public so it can be exercised directly: the script-location fallback
     * cannot be tested through boot() on a command line, where the "script" is
     * phpunit rather than a web request.
     *
     * @param string $configuredUrl the path part of APP_URL, if set
     * @param string $scriptName    $_SERVER['SCRIPT_NAME'] for the request
     * @param bool   $isCli         true under the console cron commands
     */
    public static function detect(
        string $configuredUrl,
        string $scriptName,
        bool $isCli,
        string $requestUri = ''
    ): string {
        $configuredPath = '';
        if ($configuredUrl !== '') {
            $urlPath = parse_url($configuredUrl, PHP_URL_PATH);
            if (is_string($urlPath) && trim($urlPath, '/') !== '') {
                $configuredPath = '/' . trim($urlPath, '/');
            }
        }

        // Console commands (cron: mail:work and friends) have no request to
        // learn from. Use what is configured: base_url() and the gateway return
        // URLs are built from site_url, so a folder there still matters.
        if ($isCli) {
            return $configuredPath;
        }

        $scriptDirectory = self::scriptDirectory($scriptName);

        // Evidence beats settings: the folder the browser actually asked for is
        // the folder the site is served from.
        if ($configuredPath !== '' && self::requestIsUnder($requestUri, $configuredPath)) {
            return $configuredPath;
        }

        if ($scriptDirectory !== '' && self::requestIsUnder($requestUri, $scriptDirectory)) {
            return $scriptDirectory;
        }

        // Neither candidate matches the request. If site_url was set at all it
        // is the owner's explicit statement about where the site lives —
        // including when it deliberately has no folder (the domain root).
        if ($configuredUrl !== '') {
            return $configuredPath;
        }

        // site_url is empty, so the launcher's location is all we have.
        return $scriptDirectory;
    }

    /** The folder a web script is served from: /donate/index.php -> /donate. */
    private static function scriptDirectory(string $scriptName): string
    {
        $script = str_replace('\\', '/', $scriptName);

        if ($script === '') {
            return '';
        }

        $directory = str_contains(basename($script), '.') ? dirname($script) : $script;
        $directory = rtrim(str_replace('\\', '/', $directory), '/');

        return ($directory === '/' || $directory === '.' || $directory === '') ? '' : $directory;
    }

    /** True when a raw REQUEST_URI lives inside the given folder. */
    private static function requestIsUnder(string $requestUri, string $prefix): bool
    {
        if ($prefix === '') {
            return false;
        }

        $path = parse_url($requestUri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return false;
        }

        return $path === $prefix || str_starts_with($path, $prefix . '/');
    }

    /** Test seam: forget the detected value. */
    public static function reset(): void
    {
        self::$path = '';
        self::$booted = false;
    }

    public static function isBooted(): bool
    {
        return self::$booted;
    }
}
