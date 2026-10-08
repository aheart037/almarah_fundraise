<?php

declare(strict_types=1);

/**
 * Router script for PHP's built-in development server.
 *
 * Usage:  php -S 0.0.0.0:8080 router.php
 *
 * This mirrors what the .htaccess does on a real server: existing files
 * (assets, uploads) are served as they are, the private folders are refused,
 * and every other address goes to index.php. Without it the built-in server
 * would happily serve config.php and storage/app/app-key.txt.
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rawurldecode($path);

// Private folders: the same list the .htaccess refuses.
$private = ['app', 'bin', 'bootstrap', 'config', 'database', 'deploy', 'resources', 'routes', 'storage', 'tests', 'vendor'];

$first = strtolower(explode('/', ltrim($path, '/'))[0] ?? '');

$forbidden = static function (string $reason): bool {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Forbidden: ' . $reason;
    return true;
};

// Dotfiles (.env, .htaccess, .git …).
if (preg_match('#(^|/)\.[^/]#', $path) === 1) {
    return $forbidden('this file is private.');
}

// Anything inside a private folder.
if (in_array($first, $private, true)) {
    return $forbidden('this folder is part of the application.');
}

// Private files in the web root, by name: your database password and the
// development router itself. index.php is the one PHP file that may run.
if (in_array(strtolower(basename($path)), ['config.php', 'router.php', 'phpunit.xml'], true)) {
    return $forbidden('this file is private.');
}

// Private file types anywhere (*.sql, *.log, *.txt, *.md, *.json, *.lock, …).
if (preg_match('#\.(sql|log|md|txt|json|lock|ini|sh|phar|bak|old|orig|swp|example|dist|ne|yml|yaml)$#i', $path) === 1) {
    return $forbidden('this file type is not served.');
}

// Real static files (assets, uploaded images).
$file = __DIR__ . $path;
if ($path !== '/' && is_file($file)) {
    return false;
}

// Everything else is handled by the application.
require __DIR__ . '/index.php';

return true;
