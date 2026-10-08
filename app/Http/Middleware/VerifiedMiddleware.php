<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\HttpException;
use App\Services\AuthService;

/**
 * Requires a verified email address for actions that create public content.
 */
final class VerifiedMiddleware implements MiddlewareInterface
{
    public function __construct(private AuthService $auth)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $user = $this->auth->user();

        if ($user === null) {
            throw new HttpException(302, '', ['Location' => '/login']);
        }

        if (empty($user['email_verified_at']) && !$this->auth->hasAnyRole(['admin', 'super_admin'])) {
            throw new HttpException(302, '', ['Location' => '/verify-email']);
        }

        return $next($request);
    }
}
