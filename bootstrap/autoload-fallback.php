<?php

declare(strict_types=1);

/**
 * Fallback autoloader, used only when vendor/autoload.php is not there.
 *
 * vendor/ is written by `composer install` and is deliberately not in Git, so
 * a folder copied straight out of the repository — or a zip built without
 * running Composer — has none. bootstrap/app.php used to `require` Composer's
 * file unconditionally; that fatal happened before index.php's error handling
 * started, so the visitor saw a bare "can't currently handle this request /
 * HTTP 500" with nothing to read and no log line to follow.
 *
 * This file maps the one namespace the application needs:
 *
 *   App\  ->  app/            (PSR-4, exactly as composer.json declares it)
 *
 * plus app/Support/helpers.php, which composer.json loads as a `files` entry.
 * Nothing else in the application requires a Composer package to boot: PHPMailer
 * is used by App\Mail\SmtpTransport only while a message is being sent, and a
 * missing class there is caught and reported as a failed mail job
 * (App\Mail\MailQueue::deliver()), which the queue retries. So the site serving
 * pages, with email queued and the setup page naming the missing step, is a
 * strictly better outcome than refusing to start.
 *
 * Once `composer install` has run, vendor/autoload.php wins and this file is
 * never loaded.
 */

$almarahFallbackBasePath = dirname(__DIR__);

spl_autoload_register(static function (string $class) use ($almarahFallbackBasePath): void {
    // PSR-4: App\Foo\Bar => app/Foo/Bar.php
    if (!str_starts_with($class, 'App\\')) {
        return;
    }

    $file = $almarahFallbackBasePath . '/app/'
        . str_replace('\\', '/', substr($class, strlen('App\\'))) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

require $almarahFallbackBasePath . '/app/Support/helpers.php';
