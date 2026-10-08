<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Runtime directories the application needs to write to, and the one place
 * that knows where the web folder is.
 *
 * Since the application folder IS the web folder (that is the whole point of
 * the single-folder layout: upload it to public_html, edit config.php, done),
 * the public folder is simply the application's own folder. A 'public_path' in
 * config.php still overrides that for the unusual host that serves the site
 * from somewhere else — and the ALMARAH_WEB_ROOT constant that index.php
 * defines wins when nothing is configured at all.
 *
 * Creating these directories on boot means a fresh upload never fails with
 * "permission denied" or "no such file or directory" for a reason the site
 * owner cannot see or fix. Failures are silent by design: a read-only
 * filesystem is reported by the setup page as a checklist item, which is far
 * more useful than a fatal error on every request.
 */
final class Paths
{
    /** Directories relative to the application, with the mode they need. */
    private const APP_DIRECTORIES = [
        'storage',
        'storage/logs',
        'storage/app',
        'storage/cache',
        'storage/uploads',
    ];

    public static function ensure(string $basePath): void
    {
        $basePath = rtrim($basePath, '/');

        foreach (self::APP_DIRECTORIES as $relative) {
            self::make($basePath . '/' . $relative, 0755);
        }

        // The uploads directory inside the web root, so images work before the
        // first upload happens.
        self::make(self::publicPath($basePath) . '/uploads', 0755);
    }

    /**
     * The directory the web server serves: the application folder itself, or
     * the folder named by config.php's public_path when the host serves the
     * site from somewhere else.
     *
     * @param string|null $webRoot The folder the web server serves, normally
     *                             taken from the ALMARAH_WEB_ROOT constant that
     *                             index.php defines. Passed explicitly by
     *                             tests, which cannot define a constant twice.
     */
    public static function publicPath(string $basePath, ?string $webRoot = null): string
    {
        $configured = trim((string) Config::get('app.public_path', ''));

        if ($configured !== '' && !self::isWebAddress($configured)) {
            return rtrim($configured, '/');
        }

        // No (usable) setting: use the folder the web server actually serves,
        // which index.php recorded on the way in. This is what makes the
        // uploaded-image folder correct without the owner configuring it — and
        // it is why a wrong public_path can no longer send images somewhere the
        // browser cannot see them.
        $webRoot ??= defined('ALMARAH_WEB_ROOT') ? (string) constant('ALMARAH_WEB_ROOT') : null;
        $webRoot = $webRoot === null ? '' : rtrim($webRoot, '/');

        if ($webRoot !== '') {
            return $webRoot;
        }

        // Nothing configured and no constant defined (a console run, a test):
        // the application folder is the web folder.
        return rtrim($basePath, '/');
    }

    /** Where the application would write uploads right now, for diagnostics. */
    public static function uploadsPath(string $basePath, ?string $webRoot = null): string
    {
        return self::publicPath($basePath, $webRoot) . '/uploads';
    }

    /**
     * True when a configured path is really a URL. 'public_path' means a folder
     * on the server, and pasting the site address into it is an easy mistake
     * that would otherwise create junk directories and silently break uploads.
     */
    public static function isWebAddress(string $value): bool
    {
        return preg_match('#^(?:[a-z][a-z0-9+.-]*:|//)#i', $value) === 1;
    }

    private static function make(string $path, int $mode): void
    {
        if (is_dir($path)) {
            return;
        }

        @mkdir($path, $mode, true);
    }
}
