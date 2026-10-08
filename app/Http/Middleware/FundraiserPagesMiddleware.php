<?php

declare(strict_types=1);

namespace App\Http\Middleware;

/** Gates creation, editing, submission and pausing of fundraiser pages. */
final class FundraiserPagesMiddleware extends FundraiserCapabilityMiddleware
{
    protected function capability(): string
    {
        return 'manage_pages';
    }

    protected function disabledMessage(): string
    {
        return 'Fundraiser page management is currently disabled by an administrator.';
    }
}
