<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\HttpException;
use App\Services\AuthService;

final class GuestMiddleware implements MiddlewareInterface
{
    public function __construct(private AuthService $auth)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        if ($this->auth->check()) {
            throw new HttpException(302, '', ['Location' => $this->auth->homeUrl()]);
        }

        return $next($request);
    }
}
