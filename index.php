<?php

declare(strict_types=1);

/**
 * Almarah Foundation fundraising platform — the web entry point.
 *
 * THIS FOLDER IS THE WEBSITE. Upload it into the folder your site is served
 * from (public_html, or public_html/donate for https://yourdomain.com/donate),
 * edit config.php, and open /setup. There is nothing to copy anywhere.
 *
 * Everything the browser may reach lives in this folder: index.php, assets/
 * and uploads/. Everything it may NOT reach — the database password in
 * config.php, the encryption key in storage/app/, the application code — sits
 * in this same folder but is refused by two independent sets of rules: the
 * .htaccess here and the one inside each private folder. See START-HERE.txt.
 */

// The folder this file sits in IS the folder the web server serves. That is a
// fact rather than a setting, so it is recorded here; the application uses it
// for the one thing that has to match it — where uploaded images are written.
// config.php therefore needs no 'public_path' line at all.
define('ALMARAH_WEB_ROOT', __DIR__);

if (!is_file(__DIR__ . '/bootstrap/app.php')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');

    echo "The application files are missing from this folder.\n";
    echo "Upload the whole almarah-platform folder, not just this file.\n";
    exit;
}

use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Exceptions\HttpException;
use App\Exceptions\ValidationException;

try {
    // Everything the site needs is wired up in bootstrap/app.php. A failure in
    // there — a file that is not readable, a dependency that is not installed,
    // a config value that cannot be parsed — used to escape this script
    // entirely, because the require was outside the try block below. PHP then
    // killed the request before any of the application's own reporting ran, and
    // the browser was left with "can't currently handle this request / HTTP
    // 500": no page, no hint, and nothing in storage/logs to read.
    $app = require __DIR__ . '/bootstrap/app.php';
} catch (\Throwable $bootFailure) {
    report_boot_failure($bootFailure);
}

/** @var Logger $logger */
$logger = $app->make(Logger::class);

try {
    /** @var Session $session */
    $session = $app->make(Session::class);
    $session->start();

    /** @var Request $request */
    $request = $app->make(Request::class);

    /** @var \App\Core\Router $router */
    $router = $app->make(\App\Core\Router::class);

    /** @var View $view */
    $view = $app->make(View::class);
    $view->share('appName', (string) config('app.name'));
    $view->share('org', config('app.org', []));
    $view->share('currentPath', $request->path());

    $response = $router->dispatch($request, $app);
} catch (ValidationException $e) {
    // Validation failures outside a form context still return a safe response.
    $session = $session ?? $app->make(Session::class);
    $session->flash('_errors', $e->errors());
    $response = Response::redirect($_SERVER['HTTP_REFERER'] ?? '/');
} catch (HttpException $e) {
    $response = render_error($app, $e->statusCode(), $e->getMessage(), $e->headers());
} catch (\Throwable $e) {
    $logger->error('Unhandled exception', [
        'exception' => get_class($e),
        'message'   => $e->getMessage(),
        'file'      => $e->getFile(),
        'line'      => $e->getLine(),
    ]);

    // Before the site has been installed, a database problem is expected — the
    // owner has not filled in config.php yet, or the schema does not exist. Send
    // them to the setup page, which explains exactly what is missing, instead
    // of showing a generic error. Once installation is complete this path is
    // never taken and real errors are reported normally.
    $notInstalled = !is_file($app->basePath('storage/app/installed.lock'));

    if ($notInstalled && is_database_problem($e)) {
        $response = Response::redirect('/setup');
    } else {
        $message = (bool) config('app.debug', false)
            ? $e->getMessage() . ' — ' . $e->getFile() . ':' . $e->getLine()
            : 'Something went wrong on our side. Our team has been notified.';

        $response = render_error($app, 500, $message);
    }
}

$response->send();

/**
 * True when an exception looks like "the database is not ready yet" rather than
 * a bug: no connection, wrong credentials, or a schema that has not been
 * created. Used only to route a not-yet-installed site to /setup.
 */
function is_database_problem(Throwable $e): bool
{
    $message = $e->getMessage();

    foreach ([
        'Database connection failed',
        'SQLSTATE',
        'Unknown database',
        'Access denied for user',
        "doesn't exist",
        'Base table or view not found',
        'Connection refused',
    ] as $needle) {
        if (str_contains($message, $needle)) {
            return true;
        }
    }

    return false;
}

/**
 * Reports a failure in the boot sequence, before any service exists.
 *
 * There is no container, no logger and no view layer to lean on here, and the
 * one error page that could be rendered without them is plain text. The detail
 * goes where the installation guides already tell an owner to look: the server
 * error log, and storage/logs/app-<date>.log.
 *
 * What the browser is told depends on whether the site has been installed. In
 * the middle of an installation nothing is at risk and the owner has to act on
 * the message, so it is shown in full. Afterwards the same message could name a
 * file or a setting to a stranger, so the visitor gets a short sentence and the
 * full text stays in the logs.
 */
function report_boot_failure(Throwable $e): never
{
    $detail = sprintf(
        'The site could not start: %s in %s on line %d',
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    );

    // error_log() follows the server's own configuration, which on cPanel is
    // the file the Error Log / Metrics screens show.
    error_log('[almarah] ' . $detail . "\n" . $e->getTraceAsString());

    $logFile = __DIR__ . '/storage/logs/app-' . gmdate('Y-m-d') . '.log';
    if (is_dir(dirname($logFile)) && is_writable(dirname($logFile))) {
        @file_put_contents(
            $logFile,
            sprintf("[%s UTC] app.ERROR: %s\n%s\n", gmdate('Y-m-d H:i:s'), $detail, $e->getTraceAsString()),
            FILE_APPEND | LOCK_EX
        );
    }

    $explainInstallation = !is_file(__DIR__ . '/storage/app/installed.lock');

    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');

    echo "The site could not start.\n\n";
    echo $explainInstallation
        ? "This folder has not finished installing, so the reason is shown in full:\n  "
          . $detail . "\n\n"
          . "Common causes:\n"
          . "  * vendor/autoload.php is missing — run: composer install --no-dev --optimize-autoloader\n"
          . "    in this folder, or upload the vendor/ folder from a built package (START-HERE.txt step 4).\n"
          . "  * the PHP version selected for this domain is older than 8.2 (MultiPHP Manager).\n"
          . "  * config.php is not readable, or a file was not uploaded.\n"
          . "  * storage/ or one of its folders is not writable by the web user.\n"
        : "Something went wrong while starting the site. The reason has been written to\n"
          . "the error log of this domain and to storage/logs/, where the exact file and\n"
          . "line are recorded.\n";

    exit;
}

/**
 * Renders an error page using the public layout when possible, falling back to
 * a bare safe response when the view layer itself is the problem.
 */
function render_error($app, int $status, string $message, array $headers = []): Response
{
    /** @var Logger $logger */
    $logger = $app->make(Logger::class);

    if ($status >= 500) {
        $logger->error('HTTP error response', ['status' => $status, 'message' => $message]);
    }

    try {
        $view = $app->make(View::class);
        $view->share('appName', (string) config('app.name'));
        $view->share('org', config('app.org', []));

        // The pages that ship with the site are public/errors/404 (a styled
        // "no such page") and public/errors/error, which prints the status and
        // the message and is used for everything else, 500 included. This used
        // to look in resources/views/errors/ — a folder the application has
        // never had — so every error page missed, and the plain fallback at the
        // bottom of this function is what a visitor actually saw.
        $template = $status === 404 && $view->exists('public/errors/404')
            ? 'public/errors/404'
            : 'public/errors/error';

        $html = $view->render($template, [
            'status'  => $status,
            'message' => $message,
        ], 'layouts/public');

        return Response::html($html, $status)->withHeaders($headers);
    } catch (\Throwable $inner) {
        return Response::html(
            '<!doctype html><meta charset="utf-8"><title>Error ' . $status . '</title>'
            . '<h1>Error ' . $status . '</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>',
            $status
        )->withHeaders($headers);
    }
}
