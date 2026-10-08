<?php

declare(strict_types=1);
/**
 * Global helpers. Output escaping lives here so that every template has a
 * single, auditable escape path for untrusted data.
 */

use App\Core\App;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Money;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;

if (!function_exists('app')) {
    /**
     * The application container, or a resolved service when given an id.
     *
     *     app()                        // App\Core\App
     *     app(UploadService::class)    // resolved instance
     */
    function app(?string $abstract = null): mixed
    {
        $app = App::instance();

        return $abstract === null ? $app : $app->make($abstract);
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('e')) {
    /** Escape for HTML text and attribute contexts. */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('ejs')) {
    /** Escape a value for embedding inside a <script> block. */
    function ejs(mixed $value): string
    {
        return json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES)
            ?: 'null';
    }
}

if (!function_exists('eurl')) {
    /** Escape a value for a URL query-string context. */
    function eurl(mixed $value): string
    {
        return htmlspecialchars(rawurlencode((string) ($value ?? '')), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('base_url')) {
    function base_url(string $path = ''): string
    {
        $base = rtrim(config('app.url', '') === '' ? '' : (string) config('app.url'), '/');
        $path = ltrim($path, '/');

        // When the site is served from a subfolder (https://host/donate) that
        // folder has to be part of every link. site_url normally carries it;
        // this also covers the two cases where it does not — site_url is still
        // empty, or it was saved without the folder.
        $basePath = \App\Core\BasePath::get();
        if ($basePath !== '' && !str_ends_with($base, $basePath)) {
            $base .= $basePath;
        }

        return $path === '' ? $base : $base . '/' . $path;
    }
}

if (!function_exists('url')) {
    /** Named route URL, e.g. url('fundraiser.show', ['slug' => $s]). */
    function url(string $name, array $params = []): string
    {
        /** @var \App\Core\Router $router */
        $router = app()->make(\App\Core\Router::class);
        return $router->url($name, $params);
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        $path = ltrim($path, '/');

        // Assets live in the web root, which is the application folder in this
        // layout (or wherever the host serves the site from).
        $file = \App\Core\Paths::publicPath(app()->basePath()) . '/' . $path;
        $version = is_file($file) ? (string) filemtime($file) : '0';

        return base_url($path) . '?v=' . $version;
    }
}

if (!function_exists('request')) {
    function request(): Request
    {
        /** @var Request $request */
        $request = app()->make(Request::class);
        return $request;
    }
}

if (!function_exists('session')) {
    function session(): Session
    {
        /** @var Session $session */
        $session = app()->make(Session::class);
        return $session;
    }
}

if (!function_exists('view')) {
    function view(): View
    {
        /** @var View $view */
        $view = app()->make(View::class);
        return $view;
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        /** @var Csrf $csrf */
        $csrf = app()->make(Csrf::class);
        return $csrf->field();
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        /** @var Csrf $csrf */
        $csrf = app()->make(Csrf::class);
        return $csrf->token();
    }
}

if (!function_exists('old')) {
    /** Previously submitted value, echoed safely. */
    function old(string $key, mixed $default = ''): string
    {
        $old = session()->getFlash('_old', []);
        $value = is_array($old) && array_key_exists($key, $old) ? $old[$key] : $default;
        return e($value);
    }
}

if (!function_exists('errors')) {
    /** @return array<string,string> */
    function errors(): array
    {
        $errors = session()->getFlash('_errors', []);
        return is_array($errors) ? $errors : [];
    }
}

if (!function_exists('error_for')) {
    function error_for(string $field): string
    {
        $errors = errors();
        return isset($errors[$field]) ? e($errors[$field]) : '';
    }
}

if (!function_exists('flash')) {
    function flash(string $key, mixed $default = null): mixed
    {
        return session()->getFlash($key, $default);
    }
}

if (!function_exists('money')) {
    function money(int $minor, string $currency = 'PKR'): string
    {
        return Money::format($minor, $currency);
    }
}

if (!function_exists('money_short')) {
    function money_short(int $minor, string $currency = 'PKR'): string
    {
        return Money::short($minor, $currency);
    }
}

if (!function_exists('dt')) {
    /** Format a UTC database timestamp for display. */
    function dt(?string $utc, string $format = 'j M Y'): string
    {
        if ($utc === null || $utc === '') {
            return '—';
        }
        $ts = strtotime($utc . ' UTC');
        return $ts === false ? '—' : gmdate($format, $ts);
    }
}

if (!function_exists('relative_time')) {
    function relative_time(?string $utc): string
    {
        if ($utc === null || $utc === '') {
            return '—';
        }
        $ts = strtotime($utc . ' UTC');
        if ($ts === false) {
            return '—';
        }
        $diff = time() - $ts;
        if ($diff < 60) {
            return 'just now';
        }
        if ($diff < 3600) {
            $m = (int) floor($diff / 60);
            return $m . ($m === 1 ? ' minute ago' : ' minutes ago');
        }
        if ($diff < 86400) {
            $h = (int) floor($diff / 3600);
            return $h . ($h === 1 ? ' hour ago' : ' hours ago');
        }
        if ($diff < 604800) {
            $d = (int) floor($diff / 86400);
            return $d . ($d === 1 ? ' day ago' : ' days ago');
        }
        return gmdate('j M Y', $ts);
    }
}

if (!function_exists('initials')) {
    function initials(string $name): string
    {
        $parts = Str_initials($name);
        return $parts['first'] . $parts['second'];
    }
}

if (!function_exists('Str_initials')) {
    function Str_initials(string $name): array
    {
        return \App\Core\Str::initials($name);
    }
}

if (!function_exists('status_badge_class')) {
    function status_badge_class(string $status): string
    {
        return match ($status) {
            'completed', 'published', 'approved', 'active', 'sent', 'reactivated',
            'accepted', 'accepted_pending'                                          => 'badge badge-green',
            'processing', 'pending', 'pending_review', 'draft', 'queued', 'paused',
            'changes_requested', 'sending'                                          => 'badge badge-amber',
            'failed', 'rejected', 'cancelled', 'suspended', 'abandoned', 'expired',
            'revoked'                                                               => 'badge badge-red',
            'refunded', 'partial_refund'                                            => 'badge badge-purple',
            default                                                                 => 'badge badge-grey',
        };
    }
}

if (!function_exists('site_brand_asset')) {
    /** Resolve one of the uploaded brand assets with a file-mtime cache key. */
    function site_brand_asset(string $settingKey): ?string
    {
        if (!in_array($settingKey, ['site.header_logo', 'site.footer_logo', 'site.favicon'], true)) {
            return null;
        }

        $path = app(\App\Services\SettingsService::class)->get($settingKey, '');
        if (!is_string($path) || $path === '' || str_contains($path, '..') || str_contains($path, '\\')) {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)
            || ($settingKey === 'site.favicon' && $extension !== 'png')) {
            return null;
        }

        $uploadDirectory = trim((string) Config::get('security.uploads.directory', 'uploads'), '/');
        $brandingDirectory = $uploadDirectory . '/branding';
        if (!str_starts_with($path, $brandingDirectory . '/')) {
            return null;
        }

        $absolutePath = \App\Core\Paths::publicPath(app()->basePath()) . '/' . $path;
        if (!is_file($absolutePath)) {
            return null;
        }

        // asset() adds a ?v=filemtime suffix, and each replacement also uses a
        // random filename, so logos and favicons cannot remain browser-stale.
        return asset($path);
    }
}

if (!function_exists('can_fundraiser_capability')) {
    /** Capability check for views; server-side route middleware remains authoritative. */
    function can_fundraiser_capability(string $capability): bool
    {
        $auth = app(\App\Services\AuthService::class);
        if ($auth->isAdmin()) {
            return true;
        }

        if ($auth->check() && !$auth->hasRole('fundraiser')) {
            return false;
        }

        return app(\App\Services\FundraiserCapabilityService::class)->enabled($capability);
    }
}

if (!function_exists('abort')) {
    function abort(int $status, string $message = ''): never
    {
        throw new \App\Exceptions\HttpException($status, $message);
    }
}
