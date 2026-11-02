<?php

declare(strict_types=1);

/**
 * Test bootstrap: the application's own autoloader and helpers, no framework.
 * Run the report tests with:   php tests/reports_test.php
 *
 * They need an EMPTY MySQL/MariaDB database that is safe to wipe. Its name must
 * contain "test". Point the tests at it with environment variables:
 *   TEST_DB_HOST (127.0.0.1)  TEST_DB_PORT (3306)  TEST_DB_NAME (almarah_test)
 *   TEST_DB_USER              TEST_DB_PASS
 */

$base = dirname(__DIR__);
require_once $base . '/bootstrap/autoload-fallback.php';

// Tests\Foo\Bar => tests/Foo/Bar.php
spl_autoload_register(static function (string $class) use ($base): void {
    if (str_starts_with($class, 'Tests\\')) {
        $file = $base . '/tests/' . str_replace('\\', '/', substr($class, 6)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});
