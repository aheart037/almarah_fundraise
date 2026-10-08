<?php

declare(strict_types=1);

/**
 * Application bootstrap: builds the container, binds infrastructure services
 * and registers routes. Returns the App instance so both public/index.php and
 * bin/console can share exactly the same wiring.
 */

use App\Core\App;
use App\Core\BasePath;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Paths;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\SimpleConfig;
use App\Core\View;
use App\Mail\Mailer;
use App\Mail\MailQueue;
use App\Mail\SmtpTransport;
use App\Mail\TransportInterface;
use App\Payments\PaymentGatewayManager;
use App\Services\AuditService;
use App\Services\EmailTemplateService;
use App\Services\SettingsService;
use App\Services\UploadService;

$basePath = dirname(__DIR__);

// Composer's autoloader is the normal way App\ classes (and PHPMailer) are
// found. It lives in vendor/, which `composer install` writes and which is not
// in Git — so a folder copied straight out of the repository, or a zip built
// without running Composer, has no vendor/autoload.php at all. Requiring it
// unconditionally made that a PHP fatal on the first line of the boot sequence,
// before index.php had a chance to catch anything: every page of the site then
// answered with a bare "can't currently handle this request / HTTP 500", with
// no message in the log and nothing on screen to explain it.
//
// Use Composer's autoloader whenever it exists. When it does not, boot on the
// built-in fallback so the site still serves pages and the setup page can name
// the missing step; see bootstrap/autoload-fallback.php.
$almarahUsesComposer = is_file($basePath . '/vendor/autoload.php');

if ($almarahUsesComposer) {
    require $basePath . '/vendor/autoload.php';
} else {
    require $basePath . '/bootstrap/autoload-fallback.php';
}

// The simple configuration file (config.php) and the encryption key are set up
// before the container is built, so every config/*.php file picks them up.
// Order matters here, and it has been wrong before:
//   1. the owner's config.php is copied into the environment;
//   2. the application boots, which loads config/*.php (including the
//      'public_path' that says where the web-served uploads folder lives);
//   3. only then are the runtime directories created, because step 2 is what
//      tells Paths where to create them.
// With 1 and 3 swapped, an uploads folder configured through public_path was
// never created and the setup page reported it as "not writable".
SimpleConfig::apply($basePath);

$app = App::boot($basePath);

Paths::ensure($basePath);

// The site may be served from a subfolder of the domain (https://host/donate).
// Work out that prefix before anything builds a path or a URL from it.
BasePath::boot(Config::string('app.url'));

date_default_timezone_set((string) Config::get('app.timezone', 'UTC'));

// --- infrastructure ---------------------------------------------------------
$app->bind(Database::class, static fn (): Database => new Database(Config::array('database')));
$app->bind(Logger::class, static fn (): Logger => new Logger($basePath . '/storage/logs'));
$app->bind(Session::class, static fn (): Session => new Session(Config::array('security.session')));
$app->bind(Csrf::class, static fn (App $c): Csrf => new Csrf($c->make(Session::class)));
$app->bind(View::class, static fn (): View => new View($basePath));
$app->bind(RateLimiter::class, static fn (App $c): RateLimiter => new RateLimiter($c->make(Database::class)));

// The request object is a singleton for the current request lifecycle.
if (PHP_SAPI !== 'cli') {
    $app->bind(Request::class, static fn (): Request => new Request());
}

// --- settings and mail ------------------------------------------------------
// Uploads must land in the directory the web server actually serves. In this
// package that is the application folder itself (upload it to public_html or
// public_html/donate and it is served from there); a PUBLIC_PATH setting, or a
// host that serves the site from elsewhere, is handled inside Paths.
$publicPath = Paths::publicPath($basePath);

$app->bind(UploadService::class, static fn (App $c): UploadService => new UploadService(
    $c->make(Logger::class),
    $publicPath
));

$app->bind(SettingsService::class, static fn (App $c): SettingsService => new SettingsService(
    $c->make(Database::class),
    $c->make(Logger::class),
    Config::array('payments')
));

$app->bind(AuditService::class, static fn (App $c): AuditService => new AuditService(
    $c->make(Database::class),
    $c->make(Logger::class)
));

$app->bind(TransportInterface::class, static function (App $c): TransportInterface {
    /** @var SettingsService $settings */
    $settings = $c->make(SettingsService::class);
    return new SmtpTransport(array_merge(Config::array('mail'), $settings->mailOverrides()), $c->make(Logger::class));
});
$app->bind(MailQueue::class, static fn (App $c): MailQueue => new MailQueue(
    $c->make(Database::class),
    $c->make(TransportInterface::class),
    $c->make(EmailTemplateService::class),
    $c->make(Logger::class),
    Config::array('mail')
));
$app->bind(Mailer::class, static fn (App $c): Mailer => new Mailer(
    $c->make(MailQueue::class),
    $c->make(EmailTemplateService::class),
    $c->make(Logger::class)
));

$app->bind(PaymentGatewayManager::class, static fn (App $c): PaymentGatewayManager => new PaymentGatewayManager(
    $c->make(SettingsService::class),
    $c->make(Database::class),
    $c->make(Logger::class)
));

// A site running on the fallback autoloader is a site whose dependencies were
// never installed. Email is the one thing that notices, so say plainly what is
// missing and where to read about it — in the log the guides point at, rather
// than as a fatal on every request.
if (!$almarahUsesComposer) {
    $app->make(Logger::class)->error(
        'Dependencies are not installed: vendor/autoload.php is missing, so the'
        . ' built-in autoloader is being used. Pages work; sending email needs'
        . ' PHPMailer. Run "composer install --no-dev --optimize-autoloader" in'
        . ' this folder, or upload the vendor/ folder from a built package.'
        . ' See START-HERE.txt step 4.'
    );
}

// --- routes -----------------------------------------------------------------
$app->bind(Router::class, static function (App $c) use ($basePath): Router {
    $router = new Router();
    $request = PHP_SAPI === 'cli' ? new Request('GET', '/', [], [], [], []) : $c->make(Request::class);

    foreach (['web.php', 'fundraiser.php', 'admin.php'] as $file) {
        $registrar = require $basePath . '/routes/' . $file;
        if (is_callable($registrar)) {
            $registrar($router);
        }
    }

    return $router;
});

return $app;
