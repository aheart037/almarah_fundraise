<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Tiny service container + application kernel.
 */
final class App
{
    private static ?App $instance = null;

    /** @var array<string, mixed> */
    private array $bindings = [];

    /** @var array<string, bool> */
    private array $shared = [];

    private string $basePath;

    private function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
    }

    public static function boot(string $basePath): App
    {
        if (self::$instance instanceof App) {
            return self::$instance;
        }

        $app = new self($basePath);
        self::$instance = $app;

        Env::load($app->basePath . '/.env');
        Config::load($app->basePath . '/config');

        return $app;
    }

    public static function instance(): App
    {
        if (!self::$instance instanceof App) {
            throw new RuntimeException('Application has not been booted.');
        }
        return self::$instance;
    }

    public function basePath(string $append = ''): string
    {
        return $this->basePath . ($append !== '' ? '/' . ltrim($append, '/') : '');
    }

    public function bind(string $id, callable $factory, bool $shared = true): void
    {
        $this->bindings[$id] = $factory;
        $this->shared[$id] = $shared;
    }

    /** Register an already-built object in the container. */
    public function singleton(string $id, object $object): void
    {
        $this->bindings[$id] = static fn () => $object;
        $this->shared[$id] = true;
    }

    public function has(string $id): bool
    {
        return isset($this->bindings[$id]);
    }

    public function make(string $id): mixed
    {
        if (!isset($this->bindings[$id])) {
            return $this->autowire($id);
        }

        $resolved = ($this->bindings[$id])($this);

        if (($this->shared[$id] ?? true) === true && is_object($resolved)) {
            $this->bindings[$id] = static fn () => $resolved;
        }

        return $resolved;
    }

    /**
     * Constructor-injection autowiring for controllers and services. Scalar
     * parameters must be bound explicitly; they are never guessed.
     */
    private function autowire(string $id): object
    {
        if (!class_exists($id)) {
            throw new RuntimeException("Nothing bound for [{$id}] and the class does not exist.");
        }

        $reflection = new \ReflectionClass($id);
        if (!$reflection->isInstantiable()) {
            throw new RuntimeException("Cannot instantiate [{$id}].");
        }

        $constructor = $reflection->getConstructor();
        if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
            $instance = $reflection->newInstance();
            $this->singleton($id, $instance);
            return $instance;
        }

        $arguments = [];
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();
            if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
                if ($parameter->isDefaultValueAvailable()) {
                    $arguments[] = $parameter->getDefaultValue();
                    continue;
                }
                throw new RuntimeException(sprintf(
                    'Cannot autowire parameter $%s of [%s]; bind the class explicitly.',
                    $parameter->getName(),
                    $id
                ));
            }

            /** @var class-string $dependency */
            $dependency = $type->getName();
            $arguments[] = $this->make($dependency);
        }

        $instance = $reflection->newInstanceArgs($arguments);
        $this->singleton($id, $instance);

        return $instance;
    }
}
