<?php

declare(strict_types=1);

/**
 * Fundraiser dashboard. Every route is behind authentication, the fundraiser
 * role check and CSRF protection for unsafe methods.
 */

use App\Core\Router;
use App\Http\Controllers\Fundraiser\DashboardController;
use App\Http\Controllers\Fundraiser\FundraiserController;
use App\Http\Controllers\Fundraiser\ProfileController;
use App\Http\Controllers\Fundraiser\TeamController;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\CsrfMiddleware;
use App\Http\Middleware\FundraiserMiddleware;
use App\Http\Middleware\FundraiserPagesMiddleware;
use App\Http\Middleware\FundraiserUpdatesMiddleware;
use App\Http\Middleware\TeamFundraisingMiddleware;

$auth = [AuthMiddleware::class, FundraiserMiddleware::class];
$authCsrf = [AuthMiddleware::class, FundraiserMiddleware::class, CsrfMiddleware::class];
$pageAccess = [...$auth, FundraiserPagesMiddleware::class];
$pageAccessCsrf = [...$pageAccess, CsrfMiddleware::class];
$updateAccessCsrf = [...$auth, FundraiserUpdatesMiddleware::class, CsrfMiddleware::class];
$teamAccess = [...$auth, TeamFundraisingMiddleware::class];
$teamAccessCsrf = [...$teamAccess, CsrfMiddleware::class];

return static function (Router $router) use ($auth, $authCsrf, $pageAccess, $pageAccessCsrf, $updateAccessCsrf, $teamAccess, $teamAccessCsrf): void {
    $router->get('/dashboard', [DashboardController::class, 'index'], $auth, 'dashboard');

    // --- fundraisers --------------------------------------------------------
    $router->get('/dashboard/fundraisers', [FundraiserController::class, 'index'], $auth, 'dashboard.fundraisers');
    $router->get('/dashboard/fundraisers/create', [FundraiserController::class, 'create'], $pageAccess, 'dashboard.fundraisers.create');
    $router->post('/dashboard/fundraisers', [FundraiserController::class, 'store'], $pageAccessCsrf, 'dashboard.fundraisers.store');
    $router->get('/dashboard/fundraisers/{id}/edit', [FundraiserController::class, 'edit'], $pageAccess, 'dashboard.fundraisers.edit');
    $router->post('/dashboard/fundraisers/{id}', [FundraiserController::class, 'update'], $pageAccessCsrf, 'dashboard.fundraisers.update');
    $router->post('/dashboard/fundraisers/{id}/submit', [FundraiserController::class, 'submit'], $pageAccessCsrf, 'dashboard.fundraisers.submit');
    $router->post('/dashboard/fundraisers/{id}/pause', [FundraiserController::class, 'pause'], $pageAccessCsrf, 'dashboard.fundraisers.pause');

    $router->get('/dashboard/fundraisers/{id}/updates', [FundraiserController::class, 'updates'], $auth, 'dashboard.fundraisers.updates');
    $router->post('/dashboard/fundraisers/{id}/updates', [FundraiserController::class, 'storeUpdate'], $updateAccessCsrf, 'dashboard.fundraisers.updates.store');
    $router->post('/dashboard/fundraisers/{id}/updates/{updateId}/delete', [FundraiserController::class, 'deleteUpdate'], $updateAccessCsrf, 'dashboard.fundraisers.updates.delete');

    $router->get('/dashboard/fundraisers/{id}/donations', [FundraiserController::class, 'donations'], $auth, 'dashboard.fundraisers.donations');
    $router->get('/dashboard/fundraisers/{id}/donations/export', [FundraiserController::class, 'exportDonations'], $auth, 'dashboard.fundraisers.donations.export');

    // --- teams --------------------------------------------------------------
    $router->get('/dashboard/teams', [TeamController::class, 'index'], $teamAccess, 'dashboard.teams');
    $router->post('/dashboard/teams', [TeamController::class, 'store'], $teamAccessCsrf, 'dashboard.teams.store');
    $router->get('/dashboard/teams/{id}', [TeamController::class, 'show'], $teamAccess, 'dashboard.teams.show');
    $router->post('/dashboard/teams/{id}', [TeamController::class, 'update'], $teamAccessCsrf, 'dashboard.teams.update');
    $router->post('/dashboard/teams/{id}/invite', [TeamController::class, 'invite'], $teamAccessCsrf, 'dashboard.teams.invite');
    $router->post('/dashboard/teams/{id}/members/{userId}/remove', [TeamController::class, 'removeMember'], $teamAccessCsrf, 'dashboard.teams.members.remove');

    // Team invitation acceptance happens on a public-ish URL but requires login.
    $router->get('/team-invitations/{token}', [TeamController::class, 'acceptInvitation'], $teamAccess, 'teams.invitations.accept');

    // --- account ------------------------------------------------------------
    $router->get('/dashboard/profile', [ProfileController::class, 'profile'], $auth, 'dashboard.profile');
    $router->post('/dashboard/profile', [ProfileController::class, 'updateProfile'], $authCsrf, 'dashboard.profile.update');
    $router->get('/dashboard/security', [ProfileController::class, 'security'], $auth, 'dashboard.security');
    $router->post('/dashboard/security/password', [ProfileController::class, 'updatePassword'], $authCsrf, 'dashboard.security.password');
    $router->get('/dashboard/email-preferences', [ProfileController::class, 'emailPreferences'], $auth, 'dashboard.email');
    $router->post('/dashboard/email-preferences', [ProfileController::class, 'updateEmailPreferences'], $authCsrf, 'dashboard.email.update');
};
