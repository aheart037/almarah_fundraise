<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\HttpException;

/**
 * Pattern-matching router with middleware groups and named routes.
 */
final class Router
{
    /** @var array<int, array{method:string, pattern:string, regex:string, handler:mixed, middleware:array<int,string>, name:?string}> */
    private array $routes = [];

    /** @var array<string,string> */
    private array $named = [];

    /** @var array<string,string> */
    private array $patterns = [
        'id'   => '[0-9]+',
        'slug' => '[a-z0-9]+(?:-[a-z0-9]+)*',
        'uuid' => '[0-9a-fA-F-]{36}',
        'token'=> '[A-Za-z0-9_\-]{16,128}',
    ];

    public function addPattern(string $name, string $regex): void
    {
        $this->patterns[$name] = $regex;
    }

    /** @param array<int,string> $middleware */
    public function get(string $pattern, array|callable $handler, array $middleware = [], ?string $name = null): void
    {
        $this->add('GET', $pattern, $handler, $middleware, $name);
    }

    /** @param array<int,string> $middleware */
    public function post(string $pattern, array|callable $handler, array $middleware = [], ?string $name = null): void
    {
        $this->add('POST', $pattern, $handler, $middleware, $name);
    }

    /** @param array<int,string> $middleware */
    public function any(string $pattern, array|callable $handler, array $middleware = [], ?string $name = null): void
    {
        foreach (['GET', 'POST'] as $method) {
            $this->add($method, $pattern, $handler, $middleware, $name);
        }
    }

    /** @param array<int,string> $middleware */
    public function add(string $method, string $pattern, array|callable $handler, array $middleware = [], ?string $name = null): void
    {
        $regex = $this->compile($pattern);
        $this->routes[] = [
            'method'     => strtoupper($method),
            'pattern'    => $pattern,
            'regex'      => $regex,
            'handler'    => $handler,
            'middleware' => $middleware,
            'name'       => $name,
        ];
        if ($name !== null) {
            $this->named[$name] = $pattern;
        }
    }

    private function compile(string $pattern): string
    {
        $regex = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#',
            function (array $m): string {
                $name = $m[1];
                $rule = $this->patterns[$name] ?? '[^/]+';
                return '(?P<' . $name . '>' . $rule . ')';
            },
            $pattern
        ) ?: $pattern;

        return '#^' . $regex . '$#';
    }

    public function hasNamed(string $name): bool
    {
        return isset($this->named[$name]);
    }

    /** @param array<string,string|int> $params */
    public function url(string $name, array $params = []): string
    {
        if (!isset($this->named[$name])) {
            throw new HttpException(500, "Route [{$name}] is not defined.");
        }
        $pattern = $this->named[$name];
        foreach ($params as $key => $value) {
            $pattern = str_replace('{' . $key . '}', rawurlencode((string) $value), $pattern);
        }

        // Named routes are declared without the folder prefix; add it back so
        // url('fundraiser.show', ...) links work from a subfolder too.
        return BasePath::prefix($pattern);
    }

    public function dispatch(Request $request, App $app): Response
    {
        $method = $request->method();
        $path = $request->path();
        $pathMatchedButNotMethod = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }
            if ($route['method'] !== $method) {
                $pathMatchedButNotMethod = true;
                continue;
            }

            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            $request->setRouteParams($params);

            return $this->runWithMiddleware($route, $request, $app);
        }

        // HEAD falls back to GET semantics.
        if ($method === 'HEAD') {
            $request->setAttribute('_head', true);
            foreach ($this->routes as $route) {
                if ($route['method'] === 'GET' && preg_match($route['regex'], $path, $matches)) {
                    $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                    $request->setRouteParams($params);
                    return $this->runWithMiddleware($route, $request, $app);
                }
            }
        }

        throw new HttpException($pathMatchedButNotMethod ? 405 : 404);
    }

    /** @param array{handler:mixed, middleware:array<int,string>} $route */
    private function runWithMiddleware(array $route, Request $request, App $app): Response
    {
        $pipeline = array_reverse($route['middleware']);
        $destination = fn (Request $req): Response => $this->invoke($route['handler'], $req, $app);

        $next = $destination;
        foreach ($pipeline as $middlewareClass) {
            $inner = $next;
            $next = function (Request $req) use ($middlewareClass, $app, $inner): Response {
                /** @var \App\Http\Middleware\MiddlewareInterface $instance */
                $instance = $app->make($middlewareClass);
                return $instance->handle($req, $inner);
            };
        }

        return $next($request);
    }

    private function invoke(mixed $handler, Request $request, App $app): Response
    {
        if (is_callable($handler) && !is_array($handler)) {
            $result = $handler($request);
            return $result instanceof Response ? $result : Response::html((string) $result);
        }

        if (is_array($handler)) {
            [$class, $method] = $handler;
            if (!class_exists($class)) {
                throw new HttpException(500, "Controller [{$class}] not found.");
            }
            /** @var object $controller */
            $controller = $app->make($class);
            if (!method_exists($controller, $method)) {
                throw new HttpException(500, "Action [{$class}::{$method}] not found.");
            }
            $result = $controller->{$method}($request);
            return $result instanceof Response ? $result : Response::html((string) $result);
        }

        throw new HttpException(500, 'Invalid route handler.');
    }

    /** @return array<int, string> */
    public function routeList(): array
    {
        $out = [];
        foreach ($this->routes as $route) {
            $out[] = sprintf('%-6s %s%s', $route['method'], $route['pattern'], $route['name'] ? '  [' . $route['name'] . ']' : '');
        }
        return $out;
    }
}
