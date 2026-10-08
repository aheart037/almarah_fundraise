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

$auth = [AuthMiddleware::class, FundraiserMiddleware::class];
$authCsrf = [AuthMiddleware::class, FundraiserMiddleware::class, CsrfMiddleware::class];

return static function (Router $router) use ($auth, $authCsrf): void {
    $router->get('/dashboard', [DashboardController::class, 'index'], $auth, 'dashboard');

    // --- fundraisers --------------------------------------------------------
    $router->get('/dashboard/fundraisers', [FundraiserController::class, 'index'], $auth, 'dashboard.fundraisers');
    $router->get('/dashboard/fundraisers/create', [FundraiserController::class, 'create'], $auth, 'dashboard.fundraisers.create');
    $router->post('/dashboard/fundraisers', [FundraiserController::class, 'store'], $authCsrf, 'dashboard.fundraisers.store');
    $router->get('/dashboard/fundraisers/{id}/edit', [FundraiserController::class, 'edit'], $auth, 'dashboard.fundraisers.edit');
    $router->post('/dashboard/fundraisers/{id}', [FundraiserController::class, 'update'], $authCsrf, 'dashboard.fundraisers.update');
    $router->post('/dashboard/fundraisers/{id}/submit', [FundraiserController::class, 'submit'], $authCsrf, 'dashboard.fundraisers.submit');
    $router->post('/dashboard/fundraisers/{id}/pause', [FundraiserController::class, 'pause'], $authCsrf, 'dashboard.fundraisers.pause');

    $router->get('/dashboard/fundraisers/{id}/updates', [FundraiserController::class, 'updates'], $auth, 'dashboard.fundraisers.updates');
    $router->post('/dashboard/fundraisers/{id}/updates', [FundraiserController::class, 'storeUpdate'], $authCsrf, 'dashboard.fundraisers.updates.store');
    $router->post('/dashboard/fundraisers/{id}/updates/{updateId}/delete', [FundraiserController::class, 'deleteUpdate'], $authCsrf, 'dashboard.fundraisers.updates.delete');

    $router->get('/dashboard/fundraisers/{id}/donations', [FundraiserController::class, 'donations'], $auth, 'dashboard.fundraisers.donations');
    $router->get('/dashboard/fundraisers/{id}/donations/export', [FundraiserController::class, 'exportDonations'], $auth, 'dashboard.fundraisers.donations.export');

    // --- teams --------------------------------------------------------------
    $router->get('/dashboard/teams', [TeamController::class, 'index'], $auth, 'dashboard.teams');
    $router->post('/dashboard/teams', [TeamController::class, 'store'], $authCsrf, 'dashboard.teams.store');
    $router->get('/dashboard/teams/{id}', [TeamController::class, 'show'], $auth, 'dashboard.teams.show');
    $router->post('/dashboard/teams/{id}', [TeamController::class, 'update'], $authCsrf, 'dashboard.teams.update');
    $router->post('/dashboard/teams/{id}/invite', [TeamController::class, 'invite'], $authCsrf, 'dashboard.teams.invite');
    $router->post('/dashboard/teams/{id}/members/{userId}/remove', [TeamController::class, 'removeMember'], $authCsrf, 'dashboard.teams.members.remove');

    // Team invitation acceptance happens on a public-ish URL but requires login.
    $router->get('/team-invitations/{token}', [TeamController::class, 'acceptInvitation'], $auth, 'teams.invitations.accept');

    // --- account ------------------------------------------------------------
    $router->get('/dashboard/profile', [ProfileController::class, 'profile'], $auth, 'dashboard.profile');
    $router->post('/dashboard/profile', [ProfileController::class, 'updateProfile'], $authCsrf, 'dashboard.profile.update');
    $router->get('/dashboard/security', [ProfileController::class, 'security'], $auth, 'dashboard.security');
    $router->post('/dashboard/security/password', [ProfileController::class, 'updatePassword'], $authCsrf, 'dashboard.security.password');
    $router->get('/dashboard/email-preferences', [ProfileController::class, 'emailPreferences'], $auth, 'dashboard.email');
    $router->post('/dashboard/email-preferences', [ProfileController::class, 'updateEmailPreferences'], $authCsrf, 'dashboard.email.update');
};
