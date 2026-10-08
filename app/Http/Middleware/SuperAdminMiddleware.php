<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\AuthService;

final class SuperAdminMiddleware extends RoleMiddleware
{
    public function __construct(AuthService $auth)
    {
        parent::__construct($auth, ['super_admin']);
    }
}
