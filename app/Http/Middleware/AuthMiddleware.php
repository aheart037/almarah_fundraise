<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Exceptions\HttpException;
use App\Services\AuthService;

final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private AuthService $auth, private Session $session)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        if (!$this->auth->check()) {
            $this->session->set('_intended', $request->path());

            if ($request->wantsJson()) {
                return Response::json(['error' => 'unauthenticated'], 401);
            }

            $this->session->flash('warning', 'Please sign in to continue.');
            throw new HttpException(302, '', ['Location' => '/login']);
        }

        $user = $this->auth->user();
        if ($user !== null && ($user['status'] ?? '') !== 'active') {
            $this->auth->logout();
            $this->session->flash('error', 'Your account is not active. Please contact support.');
            throw new HttpException(302, '', ['Location' => '/login']);
        }

        return $next($request);
    }
}
