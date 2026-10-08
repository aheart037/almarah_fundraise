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

$app = require __DIR__ . '/bootstrap/app.php';

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

        $file = $app->basePath('resources/views/errors/' . $status . '.php');
        $template = is_file($file) ? 'errors.' . $status : 'errors.generic';

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
