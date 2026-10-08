<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\HttpException;
use App\Services\AuthService;

/**
 * Server-side role gate. Hiding a menu item is never sufficient: every
 * protected controller action passes through this check.
 */
class RoleMiddleware implements MiddlewareInterface
{
    /** @param array<int,string> $allowedRoles */
    public function __construct(
        private AuthService $auth,
        private array $allowedRoles = []
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        if (!$this->auth->check()) {
            throw new HttpException(302, '', ['Location' => '/login']);
        }

        if (!$this->auth->hasAnyRole($this->allowedRoles)) {
            throw new HttpException(403, 'You do not have permission to access that area.');
        }

        return $next($request);
    }
}
