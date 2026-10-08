<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Csrf;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\HttpException;

/**
 * Rejects any unsafe method whose CSRF token is missing or wrong.
 * Applied to every state-changing route.
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __construct(private Csrf $csrf, private Logger $logger)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        if (in_array($request->method(), self::SAFE_METHODS, true)) {
            return $next($request);
        }

        if (!$this->csrf->check($request)) {
            $this->logger->security('CSRF validation failed', [
                'path'   => $request->path(),
                'method' => $request->method(),
                'ip'     => $request->ip(),
            ]);

            throw new HttpException(419, 'Your session expired or the form token was invalid. Please try again.');
        }

        return $next($request);
    }
}
