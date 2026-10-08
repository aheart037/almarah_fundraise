<?php

declare(strict_types=1);

namespace App\Http\Middleware;

/** Gates publishing and deleting fundraiser updates. */
final class FundraiserUpdatesMiddleware extends FundraiserCapabilityMiddleware
{
    protected function capability(): string
    {
        return 'publish_updates';
    }

    protected function disabledMessage(): string
    {
        return 'Publishing fundraiser updates is currently disabled by an administrator.';
    }
}
