<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

/**
 * Template renderer. Views are plain PHP files rendered inside a layout.
 * All output of untrusted data must pass through e() (see helpers.php).
 */
final class View
{
    /** @var array<string,mixed> */
    private array $shared = [];

    private string $viewPath;

    public function __construct(private string $basePath)
    {
        $this->viewPath = rtrim($basePath, '/') . '/resources/views';
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /** @param array<string,mixed> $data */
    public function render(string $view, array $data = [], ?string $layout = 'layouts/public'): string
    {
        $content = $this->renderRaw($view, $data);

        if ($layout === null) {
            return $content;
        }

        return $this->renderRaw($layout, array_merge($data, ['content' => $content]));
    }

    /** @param array<string,mixed> $data */
    public function renderRaw(string $view, array $data = []): string
    {
        $file = $this->viewPath . '/' . str_replace('.', '/', $view) . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("View [{$view}] not found at {$file}.");
        }

        $variables = array_merge($this->shared, $data);
        extract($variables, EXTR_SKIP);

        ob_start();
        try {
            /** @psalm-suppress UnresolvableInclude */
            require $file;
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    public function exists(string $view): bool
    {
        return is_file($this->viewPath . '/' . str_replace('.', '/', $view) . '.php');
    }
}
