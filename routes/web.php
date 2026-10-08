<?php

declare(strict_types=1);

/**
 * Public routes: the fundraising site, the donation flow and auth.
 */

use App\Core\Router;
use App\Http\Controllers\Public\AuthController;
use App\Http\Controllers\Public\CampaignController;
use App\Http\Controllers\Public\DonationController;
use App\Http\Controllers\Public\DonationStatusController;
use App\Http\Controllers\Public\FundraiserController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\PaymentController;
use App\Http\Controllers\Public\SetupController;
use App\Http\Controllers\Public\TeamController;
use App\Http\Middleware\CsrfMiddleware;
use App\Http\Middleware\GuestMiddleware;

return static function (Router $router): void {
    // --- home & discovery ---------------------------------------------------
    // First-time setup. Self-locking: it refuses to run once an administrator
    // exists, and it never uses the site layouts so it still works when the
    // database is unreachable. See app/Http/Controllers/Public/SetupController.
    $router->get('/setup', [SetupController::class, 'index'], [], 'setup');
    $router->post('/setup', [SetupController::class, 'run'], [], 'setup.run');

    $router->get('/', [HomeController::class, 'index'], [], 'home');
    $router->get('/about-us', [PageController::class, 'about'], [], 'about');

    $router->get('/fundraisers', [FundraiserController::class, 'index'], [], 'fundraisers.index');
    $router->get('/fundraisers/{slug}', [FundraiserController::class, 'show'], [], 'fundraisers.show');

    $router->get('/teams', [TeamController::class, 'index'], [], 'teams.index');
    $router->get('/teams/{slug}', [TeamController::class, 'show'], [], 'teams.show');

    $router->get('/campaigns', [CampaignController::class, 'index'], [], 'campaigns.index');
    $router->get('/campaigns/{slug}', [CampaignController::class, 'show'], [], 'campaigns.show');

    $router->get('/faq', [PageController::class, 'faq'], [], 'faq');
    $router->get('/privacy-policy', [PageController::class, 'privacy'], [], 'privacy');
    $router->get('/support', [PageController::class, 'support'], [], 'support');
    $router->get('/terms', [PageController::class, 'terms'], [], 'terms');

    // --- donation flow ------------------------------------------------------
    $router->get('/donate/{target}', [DonationController::class, 'form'], [], 'donate.form');
    $router->post('/donate/{target}', [DonationController::class, 'submit'], [CsrfMiddleware::class], 'donate.submit');

    // Gateway return trips. Both GET (Meezan) and POST (Etisalat) land here.
    $router->get('/payments/meezan/return', [PaymentController::class, 'meezanReturn'], [], 'payments.meezan.return');
    $router->post('/payments/meezan/callback', [PaymentController::class, 'meezanReturn'], [], 'payments.meezan.callback');
    $router->get('/payments/etisalat/return', [PaymentController::class, 'etisalatReturn'], [], 'payments.etisalat.return');
    $router->post('/payments/etisalat/callback', [PaymentController::class, 'etisalatReturn'], [], 'payments.etisalat.callback');

    // Internal hosted-form bridge: auto-POSTs the stored TransactionID.
    $router->get('/payments/etisalat/bridge/{txn}', [PaymentController::class, 'etisalatBridge'], [], 'payments.etisalat.bridge');

    $router->get('/donation/success', [DonationStatusController::class, 'success'], [], 'donation.success');
    $router->get('/donation/processing', [DonationStatusController::class, 'processing'], [], 'donation.processing');
    $router->get('/donation/failed', [DonationStatusController::class, 'failed'], [], 'donation.failed');
    $router->get('/donation/{reference}', [DonationStatusController::class, 'show'], [], 'donation.show');

    // --- authentication -----------------------------------------------------
    $router->get('/login', [AuthController::class, 'showLogin'], [GuestMiddleware::class], 'login');
    $router->post('/login', [AuthController::class, 'login'], [GuestMiddleware::class, CsrfMiddleware::class], 'login.attempt');
    $router->get('/register', [AuthController::class, 'showRegister'], [GuestMiddleware::class], 'register');
    $router->post('/register', [AuthController::class, 'register'], [GuestMiddleware::class, CsrfMiddleware::class], 'register.store');
    $router->post('/logout', [AuthController::class, 'logout'], [CsrfMiddleware::class], 'logout');

    $router->get('/forgot-password', [AuthController::class, 'showForgot'], [GuestMiddleware::class], 'password.request');
    $router->post('/forgot-password', [AuthController::class, 'sendReset'], [GuestMiddleware::class, CsrfMiddleware::class], 'password.email');
    $router->get('/reset-password/{token}', [AuthController::class, 'showReset'], [GuestMiddleware::class], 'password.reset');
    $router->post('/reset-password', [AuthController::class, 'reset'], [GuestMiddleware::class, CsrfMiddleware::class], 'password.update');

    $router->get('/verify-email/{token}', [AuthController::class, 'verify'], [], 'verification.verify');
    $router->get('/verify-email', [AuthController::class, 'verifyNotice'], [], 'verification.notice');
    $router->post('/verify-email', [AuthController::class, 'resendVerification'], [CsrfMiddleware::class], 'verification.resend');
};
