<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\HttpException;
use App\Services\AuthService;
use App\Services\FundraiserCapabilityService;

/** Base gate for fundraiser-only feature controls. Admins retain access. */
abstract class FundraiserCapabilityMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthService $auth,
        private FundraiserCapabilityService $capabilities
    ) {
    }

    final public function handle(Request $request, callable $next): Response
    {
        if (!$this->auth->isAdmin() && !$this->capabilities->enabled($this->capability())) {
            throw new HttpException(403, $this->disabledMessage());
        }

        return $next($request);
    }

    abstract protected function capability(): string;

    abstract protected function disabledMessage(): string;
}
