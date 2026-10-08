<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Core\BasePath;
use App\Core\Paths;
use App\Core\Request;
use App\Core\Response;
use App\Http\Controllers\Controller;
use App\Services\InstallerService;
use Throwable;

/**
 * First-time setup, at /setup.
 *
 * Design constraints, in order:
 *
 *  1. It must work when the database is unreachable, because "the database is
 *     unreachable" is one of the things it exists to diagnose. It therefore
 *     renders its own self-contained HTML and never uses the site's layouts,
 *     the view layer, or anything that queries the database to build a page.
 *  2. Installing must be one button press: fill in three lines of config.php,
 *     open this page, type a name and a password. Nothing has to be copied
 *     from a file, and there is no key to find or lose.
 *  3. It must lock itself. After a successful install the installer refuses to
 *     run again, and if the lock file is ever removed the presence of an
 *     administrator keeps it closed.
 *
 * What stops a stranger from installing the site is that the window in which
 * the installer runs at all is the few minutes between the owner uploading the
 * files and finishing the form; anyone who needs a wider window can set the
 * optional 'setup_lock' word in config.php, which turns this form into one
 * that asks for that word.
 *
 * CSRF tokens are intentionally not used here: a CSRF token protects an
 * authenticated browser session, and there is no session to protect before an
 * installation exists.
 */
final class SetupController extends Controller
{
    /**
     * The folders in the served directory that must never be reachable by URL.
     * Each one carries its own .htaccess refusal; this list is what the setup
     * page verifies.
     */
    private const PRIVATE_DIRECTORIES = [
        'app',
        'bin',
        'bootstrap',
        'config',
        'database',
        'deploy',
        'resources',
        'routes',
        'storage',
        'vendor',
    ];

    public function __construct(
        \App\Core\View $view,
        \App\Core\Session $session,
        \App\Core\Csrf $csrf,
        \App\Services\AuthService $auth,
        private InstallerService $installer
    ) {
        parent::__construct($view, $session, $csrf, $auth);
    }

    public function index(Request $request): Response
    {
        if ($this->installer->isLocked()) {
            return $this->page('Already installed', $this->installedBody(), 200);
        }

        return $this->page('Set up your site', $this->setupForm([], []), 200);
    }

    public function run(Request $request): Response
    {
        if ($this->installer->isLocked()) {
            return $this->page('Already installed', $this->installedBody(), 200);
        }

        $errors = [];

        $lockWord = trim((string) $request->input('setup_lock', ''));
        $firstName = trim((string) $request->input('first_name', ''));
        $lastName = trim((string) $request->input('last_name', ''));
        $email = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');

        $submitted = [
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'email'      => $email,
        ];

        if (!$this->installer->lockWordMatches($lockWord)) {
            $errors[] = 'That setup lock word is not correct. It is the word you typed into '
                . "'setup_lock' in config.php — check the spelling, or empty that line to remove the lock.";
        }

        $database = $this->installer->databaseStatus();
        if (!$database['ok']) {
            $errors[] = $database['error'];
        }

        if ($errors === []) {
            try {
                $result = $this->installer->install();

                $admin = $this->installer->createAdministrator([
                    'first_name' => $firstName,
                    'last_name'  => $lastName,
                    'email'      => $email,
                    'password'   => $password,
                ]);

                if (!$admin['ok']) {
                    $errors[] = $admin['error'];
                } else {
                    $this->installer->complete();

                    return $this->page('Your site is ready', $this->doneBody($email, $result), 200);
                }
            } catch (Throwable $e) {
                $this->logger()->error('Setup failed', [
                    'exception' => get_class($e),
                    'message'   => $e->getMessage(),
                ]);

                $errors[] = 'Setup stopped part-way through: ' . $e->getMessage()
                    . ' — nothing was lost, you can correct the problem and submit the form again.';
            }
        }

        return $this->page('Set up your site', $this->setupForm($submitted, $errors), 200);
    }

    private function logger(): \App\Core\Logger
    {
        return app(\App\Core\Logger::class);
    }

    // ------------------------------------------------------------------ views

    /** @param array<int,string> $errors */
    private function setupForm(array $submitted, array $errors): string
    {
        $lockWord = $this->installer->lockWord();

        $database = $this->installer->databaseStatus();
        $config = \App\Core\SimpleConfig::status(app()->basePath());
        $writable = $this->installer->writablePaths();

        $h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

        // ---- prerequisites -------------------------------------------------
        $checks = [];

        $phpOk = PHP_VERSION_ID >= 80200;
        $checks[] = [
            'ok'   => $phpOk,
            'text' => 'PHP version: ' . PHP_VERSION . ($phpOk ? '' : ' — version 8.2 or newer is required. Ask your host to change it in MultiPHP Manager.'),
        ];

        // The site also boots without Composer: a folder copied straight out of
        // Git has no vendor/, and refusing to start there is what turned into an
        // HTTP 500 with nothing to read. Pages work either way; email is the one
        // thing that needs PHPMailer, so it is named here — while the owner is
        // still reading this list — rather than after a donation silently fails.
        $dependenciesInstalled = is_file(app()->basePath('vendor/autoload.php'));
        $checks[] = [
            'ok'   => $dependenciesInstalled,
            'text' => $dependenciesInstalled
                ? 'Dependencies: vendor/ is installed'
                : 'Dependencies: vendor/autoload.php is missing, so this site cannot send email.'
                    . ' Run <em>composer install --no-dev --optimize-autoloader</em> in this folder, or'
                    . ' upload the vendor/ folder from a built package (START-HERE.txt step 4).',
        ];

        foreach ([
            'pdo_mysql' => 'Database driver (pdo_mysql)',
            'mbstring'  => 'Text handling (mbstring)',
            'openssl'   => 'Encryption (openssl)',
            'curl'      => 'Payment gateway calls (curl)',
            'fileinfo'  => 'Upload checking (fileinfo)',
        ] as $extension => $label) {
            $loaded = extension_loaded($extension);
            $checks[] = [
                'ok'   => $loaded,
                'text' => $label . ($loaded ? '' : ' — missing. Enable it in cPanel > Select PHP Version > Extensions.'),
            ];
        }

        $checks[] = [
            'ok'   => $config['configured'],
            'text' => $config['configured']
                ? 'Config file: database details filled in'
                : 'Config file: ' . $h($config['file']) . ' still needs '
                    . (count($config['missing']) === 1 ? 'one value' : count($config['missing']) . ' values')
                    . ' — see the list below.',
        ];

        $checks[] = [
            'ok'   => $database['ok'],
            'text' => $database['ok']
                ? 'Database: connected' . ($database['version'] !== '' ? ' (' . $h($database['version']) . ')' : '')
                : 'Database: not connected. ' . $h($database['error']),
        ];

        $checks[] = [
            'ok'   => $config['key_present'],
            'text' => $config['key_present']
                ? 'Encryption key: ready'
                : 'Encryption key: could not be created. Make sure storage/app is writable.',
        ];

        foreach ($writable as $path => $ok) {
            if ($ok) {
                continue;
            }
            $checks[] = [
                'ok'   => false,
                'text' => 'Not writable: ' . $h($path) . '. The application needs to write here (uploads, '
                    . 'cache and logs). In cPanel File Manager set this folder, and the folder above it, to '
                    . 'permissions 755; if the folder does not exist, create it as a subfolder of the one '
                    . 'named above it.',
            ];
        }

        // The application folder is the folder the web server serves, so the
        // private things in it (config.php, storage/app/app-key.txt, the SQL
        // migrations) are only out of reach because of the .htaccess rules
        // that ship with it. This check confirms those files are in place, and
        // tells the owner how to prove in one click that their host applies
        // them. Both matter: a host with AllowOverride switched off would
        // serve config.php, and nothing else on this page can see that from
        // inside PHP.
        $webRoot = Paths::publicPath(app()->basePath());
        $basePath = BasePath::get();

        $shielded = [];
        $unshielded = [];

        if (!is_file($webRoot . '/.htaccess')) {
            $unshielded[] = $webRoot . '/.htaccess';
        }

        foreach (self::PRIVATE_DIRECTORIES as $folder) {
            if (!is_dir($webRoot . '/' . $folder)) {
                continue;
            }

            if (!is_file($webRoot . '/' . $folder . '/.htaccess')) {
                $unshielded[] = $webRoot . '/' . $folder . '/.htaccess';
            } else {
                $shielded[] = $folder;
            }
        }

        if ($unshielded === []) {
            $checks[] = [
                'ok'   => true,
                'text' => 'Private files: closed. The rules that refuse <code>config.php</code> and the '
                    . 'application code are in place (' . count($shielded) . ' folders plus the main '
                    . '<code>.htaccess</code>).',
            ];

            // The one thing PHP cannot test for itself, in the exact form the
            // owner can: load this URL and read what comes back.
            $checks[] = [
                'ok'   => true,
                'text' => 'One-click proof your host applies those rules: open '
                    . '<a href="' . $h($this->baseUrl('config.php')) . '" target="_blank" rel="noopener">'
                    . $h($this->baseUrl('config.php')) . '</a> in a new tab. It must say '
                    . '<strong>403 Forbidden</strong>. If it shows PHP code, tell your host to enable '
                    . '<code>.htaccess</code> overrides for this folder before you go live '
                    . '(INSTALL-CPANEL.txt section 17).',
            ];
        } else {
            $checks[] = [
                'ok'   => false,
                'text' => 'Private files: <strong>' . count($unshielded) . ' of the protection files are '
                    . 'missing</strong> &mdash; ' . $h(implode(', ', array_slice($unshielded, 0, 3)))
                    . (count($unshielded) > 3 ? ' and others' : '') . '. Without them, the folder that '
                    . 'serves your pages also hands out <code>config.php</code> and the application '
                    . 'code to anyone who guesses the name. Re-upload the package with File Manager\'s '
                    . '"Show Hidden Files" switched on, then reload this page.',
            ];
        }

        // The stylesheet has to exist where the browser will ask for it — and
        // the browser asks at the folder being served, which is this one.
        if (is_file($webRoot . '/assets/css/style.css')) {
            $checks[] = [
                'ok'   => true,
                'text' => 'Website files: stylesheet and images are in place.',
            ];
        } else {
            $checks[] = [
                'ok'   => false,
                'text' => 'Website files: <code>assets</code> is missing from <code>' . $h($webRoot)
                    . '</code>, so your pages would load without any styling or images. Upload the '
                    . 'package again and extract it in the same place, keeping the hidden '
                    . '<code>.htaccess</code> files (START-HERE.txt step 1).',
            ];
        }

        // 'public_path' takes a folder on the server, not a web address.
        // Pasting the site URL there is an easy mix-up, and it silently sends
        // uploads to a nonsense directory, so it is reported here instead.
        $configuredPublic = trim((string) config('app.public_path', ''));
        if ($configuredPublic !== '' && Paths::isWebAddress($configuredPublic)) {
            $checks[] = [
                'ok'   => false,
                'text' => 'config.php: public_path is set to <strong>' . $h($configuredPublic) . '</strong>, '
                    . 'which is a web address. It must be a folder on the server, for example '
                    . '<code>/home/USERNAME/public_html</code> &mdash; or leave it empty if you linked the '
                    . 'assets folder instead (START-HERE.txt step 2b).',
            ];
        }

        // A subfolder install is worth stating plainly, because it changes what
        // every URL looks like.
        if ($basePath !== '') {
            $checks[] = [
                'ok'   => true,
                'text' => 'Serving this site from the <strong>' . $h($basePath) . '</strong> folder '
                    . '(site_url in config.php). Links and redirects include it automatically.',
            ];
        }

        if ($lockWord !== '') {
            $checks[] = [
                'ok'   => true,
                'text' => 'Setup lock: this form asks for the word in <code>setup_lock</code> in config.php before it will install.',
            ];
        }

        $checkHtml = '';
        foreach ($checks as $check) {
            $mark = $check['ok'] ? '&#10003;' : '&#10007;';
            $class = $check['ok'] ? 'ok' : 'bad';
            $checkHtml .= '<li class="' . $class . '"><span>' . $mark . '</span> ' . $check['text'] . '</li>';
        }

        $errorHtml = '';
        foreach ($errors as $error) {
            $errorHtml .= '<p class="error">' . $h($error) . '</p>';
        }

        $missingBlock = '';
        if (!$config['configured']) {
            $lines = '';
            foreach ($config['missing'] as $missing) {
                $lines .= '<li>' . $h(\App\Core\SimpleConfig::label($missing)) . '</li>';
            }
            $missingBlock = '<div class="warn"><strong>Fill this in first:</strong> '
                . '<p>Open <span class="path">' . $h($config['file']) . '</span> and complete:</p>'
                . '<ul>' . $lines . '</ul>'
                . '<p>Save the file, then reload this page.</p></div>';
        }

        $v = static fn (string $field): string => htmlspecialchars($submitted[$field] ?? '', ENT_QUOTES, 'UTF-8');

        // Enabled as soon as the database details are in the file. A database
        // that is not reachable yet is then explained as a message on submit,
        // which is more useful than a button that cannot be pressed.
        $disabled = $config['configured'] ? '' : ' disabled';

        // The word is asked for only when the owner put one in config.php; with
        // no word set the form carries nothing extra at all.
        $lockField = $lockWord !== ''
            ? '<label for="setup_lock">Setup lock word (from config.php)</label>
               <input type="text" id="setup_lock" name="setup_lock" autocomplete="off">
               <p class="hint">You set <code>setup_lock</code> in config.php, so this one word is needed before the site will install.</p>'
            : '';

        return <<<HTML
{$missingBlock}
<h2>Before we start</h2>
<ul class="checks">{$checkHtml}</ul>

<h2>Install your site</h2>
<p>Fill in your name, an email address and a password for the administrator account.
   There is no key to copy &mdash; this page does everything else by itself.</p>
{$errorHtml}

<form method="post" action="{$h($this->baseUrl('setup'))}">
  {$lockField}

  <label for="first_name">Your first name</label>
  <input type="text" id="first_name" name="first_name" value="{$v('first_name')}" required autocomplete="given-name">

  <label for="last_name">Your last name</label>
  <input type="text" id="last_name" name="last_name" value="{$v('last_name')}" autocomplete="family-name">

  <label for="email">Email address (used to sign in to the admin area)</label>
  <input type="email" id="email" name="email" value="{$v('email')}" required autocomplete="email">

  <label for="password">Choose a password</label>
  <input type="password" id="password" name="password" required autocomplete="new-password">
  <p class="hint">At least 10 characters, with at least one letter and one number.</p>

  <button type="submit"{$disabled}>Install my site</button>
</form>

<p class="fine">This page stops working the moment the site is installed, and it never installs a
   second time. If you ever need to make changes afterwards, sign in and use the admin dashboard.</p>
HTML;
    }

    private function installedBody(): string
    {
        $h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

        $login = $h($this->baseUrl('login'));

        return <<<HTML
<p>This site has already been installed, so there is nothing left to do here.</p>
<p>Sign in to reach the admin area. If you did not create this installation, someone else did:
   change your database password in cPanel, then check the administrator accounts in <em>Users</em>.
   START-HERE.txt has a section on starting over from an empty database if you need to.</p>
<p><a class="button" href="{$login}">Go to the sign-in page</a></p>
HTML;
    }

    /** @param array{migrations:array<int,string>, seeders:array<int,string>} $result */
    private function doneBody(string $email, array $result): string
    {
        $h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

        $login = $h($this->baseUrl('login'));
        $home = $h($this->baseUrl(''));

        $tables = count($result['migrations']);

        return <<<HTML
<p><strong>You are done.</strong> The database was created ({$tables} migration(s) applied) and your
   administrator account is ready.</p>
<p>Sign in with <strong>{$h($email)}</strong> and the password you just chose.</p>
<p class="fine">A few things worth doing next, whenever you are ready:</p>
<ul class="checks">
  <li class="ok"><span>&rarr;</span> In <em>Settings &rarr; Site</em>, check the support email and phone number.</li>
  <li class="ok"><span>&rarr;</span> Add your payment gateway details in <em>Settings</em> when the bank has sent them.</li>
  <li class="ok"><span>&rarr;</span> Set up the email cron job described in <em>START-HERE.txt</em> so receipts are sent.</li>
</ul>
<p><a class="button" href="{$login}">Sign in</a> &nbsp; <a href="{$home}">View the site</a></p>
HTML;
    }

    private function baseUrl(string $path): string
    {
        $base = rtrim((string) \App\Core\Config::get('app.url', ''), '/');

        if ($base === '') {
            // Nothing configured yet: use the address the browser is on, which
            // is also how the rest of the site behaves before site_url is set.
            $scheme = (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off') ? 'https' : 'http';
            $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $base = $scheme . '://' . $host;
        }

        $base = rtrim($base, '/');
        $basePath = BasePath::get();

        if ($basePath !== '' && !str_ends_with($base, $basePath)) {
            $base .= $basePath;
        }

        return $path === '' ? $base : $base . '/' . ltrim($path, '/');
    }

    /**
     * Self-contained page: inline styles only, no scripts, no external files,
     * so it renders correctly even when nothing else on the site works.
     */
    private function page(string $title, string $body, int $status = 200): Response
    {
        $h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

        $html = <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{$h($title)} &middot; Almarah Foundation setup</title>
<style>
  :root { color-scheme: light; }
  * { box-sizing: border-box; }
  body { margin: 0; padding: 28px 16px 60px; background: #fdf5f8; color: #333;
         font: 15px/1.65 -apple-system, "Helvetica Neue", Helvetica, Arial, sans-serif; }
  .wrap { max-width: 720px; margin: 0 auto; }
  .card { background: #fff; border-radius: 15px; padding: 30px 32px 34px;
          box-shadow: 0 .3125rem 1rem -.1875rem rgba(0,0,0,.3); }
  h1 { margin: 0 0 4px; font-size: 23px; color: #1a0810; }
  .brand { font-size: 13px; letter-spacing: .1em; text-transform: uppercase; color: #a92d63;
           font-weight: 700; margin-bottom: 14px; }
  h2 { font-size: 16px; margin: 30px 0 12px; color: #1a0810; }
  p { margin: 0 0 14px; }
  ul.checks { list-style: none; margin: 0 0 6px; padding: 0; }
  ul.checks li { padding: 6px 0 6px 24px; position: relative; font-size: 14px; }
  ul.checks li span { position: absolute; left: 0; font-weight: 700; }
  ul.checks li.ok span { color: #1c7a41; }
  ul.checks li.bad span { color: #a3231b; }
  ul.checks li.bad { color: #a3231b; }
  .path { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 13px;
          background: #f7e6ee; border-radius: 5px; padding: 9px 12px; word-break: break-all; }
  .warn { background: #fff8e6; border-left: 3px solid #c9a000; border-radius: 6px;
          padding: 14px 16px; margin: 0 0 18px; font-size: 14px; }
  .warn ul { margin: 6px 0 10px; padding-left: 20px; }
  .error { background: #fdeeed; border-left: 3px solid #a3231b; border-radius: 6px;
           padding: 12px 14px; font-size: 14px; color: #7d1a15; }
  label { display: block; font-weight: 700; font-size: 13.5px; margin: 16px 0 5px; color: #2a1119; }
  input { width: 100%; padding: 11px 13px; font-size: 15px; border: 1px solid #d9cfd4;
          border-radius: 5px; background: #fff; }
  input:focus { outline: 2px solid #c4386f; outline-offset: 1px; border-color: #a92d63; }
  .hint { font-size: 12.5px; color: #717171; margin: 6px 0 0; }
  button, .button { display: inline-block; margin-top: 22px; padding: 13px 26px; border: 0;
          border-radius: 5px; background: #a92d63; color: #fff; font-size: 12px; font-weight: 700;
          letter-spacing: .09em; text-transform: uppercase; cursor: pointer;
          text-decoration: none; }
  button:hover, .button:hover { background: #8a2050; }
  button[disabled] { background: #d9cfd4; cursor: not-allowed; }
  .fine { font-size: 12.5px; color: #717171; margin-top: 22px; }
  a { color: #a92d63; }
</style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="brand">Almarah Foundation &middot; setup</div>
      <h1>{$h($title)}</h1>
      {$body}
    </div>
  </div>
</body>
</html>
HTML;

        return Response::html($html, $status)->withHeaders([
            'X-Robots-Tag' => 'noindex, nofollow',
            'Cache-Control' => 'no-store',
        ]);
    }
}
