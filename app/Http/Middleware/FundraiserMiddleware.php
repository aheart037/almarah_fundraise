<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\AuthService;

final class FundraiserMiddleware extends RoleMiddleware
{
    public function __construct(AuthService $auth)
    {
        parent::__construct($auth, ['fundraiser', 'admin', 'super_admin']);
    }
}
