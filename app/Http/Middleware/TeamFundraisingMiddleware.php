<?php

declare(strict_types=1);

namespace App\Http\Middleware;

/** Gates team pages, membership, invitations and team management. */
final class TeamFundraisingMiddleware extends FundraiserCapabilityMiddleware
{
    protected function capability(): string
    {
        return 'manage_teams';
    }

    protected function disabledMessage(): string
    {
        return 'Team fundraising is currently disabled by an administrator.';
    }
}
