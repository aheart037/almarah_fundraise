<?php

declare(strict_types=1);

/**
 * Administrator dashboard. Every route requires an admin or super_admin role,
 * enforced server-side by middleware — never by hiding a menu item.
 */

use App\Core\Router;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DonationController;
use App\Http\Controllers\Admin\EmailController;
use App\Http\Controllers\Admin\FundraiserController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\CsrfMiddleware;
use App\Http\Middleware\SuperAdminMiddleware;

$admin = [AuthMiddleware::class, AdminMiddleware::class];
$adminCsrf = [AuthMiddleware::class, AdminMiddleware::class, CsrfMiddleware::class];
$superCsrf = [AuthMiddleware::class, SuperAdminMiddleware::class, CsrfMiddleware::class];

return static function (Router $router) use ($admin, $adminCsrf, $superCsrf): void {
    $router->get('/admin', [DashboardController::class, 'index'], $admin, 'admin.dashboard');

    // --- fundraisers --------------------------------------------------------
    $router->get('/admin/fundraisers', [FundraiserController::class, 'index'], $admin, 'admin.fundraisers');
    $router->get('/admin/fundraisers/{id}', [FundraiserController::class, 'show'], $admin, 'admin.fundraisers.show');
    $router->post('/admin/fundraisers/{id}/approve', [FundraiserController::class, 'approve'], $adminCsrf, 'admin.fundraisers.approve');
    $router->post('/admin/fundraisers/{id}/reject', [FundraiserController::class, 'reject'], $adminCsrf, 'admin.fundraisers.reject');
    $router->post('/admin/fundraisers/{id}/request-changes', [FundraiserController::class, 'requestChanges'], $adminCsrf, 'admin.fundraisers.changes');
    $router->post('/admin/fundraisers/{id}/pause', [FundraiserController::class, 'pause'], $adminCsrf, 'admin.fundraisers.pause');
    $router->post('/admin/fundraisers/{id}/publish', [FundraiserController::class, 'publish'], $adminCsrf, 'admin.fundraisers.publish');
    $router->post('/admin/fundraisers/{id}/unpublish', [FundraiserController::class, 'unpublish'], $adminCsrf, 'admin.fundraisers.unpublish');
    $router->post('/admin/fundraisers/{id}/archive', [FundraiserController::class, 'archive'], $adminCsrf, 'admin.fundraisers.archive');
    $router->post('/admin/fundraisers/{id}/feature', [FundraiserController::class, 'feature'], $adminCsrf, 'admin.fundraisers.feature');
    $router->post('/admin/fundraisers/{id}/delete', [FundraiserController::class, 'destroy'], $adminCsrf, 'admin.fundraisers.delete');
    $router->post('/admin/fundraisers/{id}/updates/{updateId}/hide', [FundraiserController::class, 'hideUpdate'], $adminCsrf, 'admin.fundraisers.updates.hide');

    // --- donations and payments ---------------------------------------------
    $router->get('/admin/donations', [DonationController::class, 'index'], $admin, 'admin.donations');
    $router->get('/admin/donations/export', [DonationController::class, 'export'], $admin, 'admin.donations.export');
    $router->get('/admin/donations/{id}', [DonationController::class, 'show'], $admin, 'admin.donations.show');
    $router->post('/admin/donations/{id}/reconcile', [DonationController::class, 'reconcile'], $adminCsrf, 'admin.donations.reconcile');
    $router->post('/admin/donations/{id}/refund', [DonationController::class, 'refund'], $adminCsrf, 'admin.donations.refund');
    $router->post('/admin/transactions/{id}/reconcile', [DonationController::class, 'reconcileTransaction'], $adminCsrf, 'admin.transactions.reconcile');

    $router->get('/admin/payment-callbacks', [DonationController::class, 'callbacks'], $admin, 'admin.callbacks');

    // --- users --------------------------------------------------------------
    $router->get('/admin/users', [UserController::class, 'index'], $admin, 'admin.users');
    $router->get('/admin/users/{id}', [UserController::class, 'show'], $admin, 'admin.users.show');
    $router->post('/admin/users/{id}/suspend', [UserController::class, 'suspend'], $adminCsrf, 'admin.users.suspend');
    $router->post('/admin/users/{id}/reactivate', [UserController::class, 'reactivate'], $adminCsrf, 'admin.users.reactivate');
    $router->post('/admin/users/{id}/verify-email', [UserController::class, 'forceVerify'], $adminCsrf, 'admin.users.verify');
    $router->post('/admin/users/{id}/force-password-reset', [UserController::class, 'forcePasswordReset'], $adminCsrf, 'admin.users.reset');
    $router->post('/admin/users/{id}/roles', [UserController::class, 'updateRoles'], $superCsrf, 'admin.users.roles');

    $router->get('/admin/donors', [UserController::class, 'donors'], $admin, 'admin.donors');

    // --- content ------------------------------------------------------------
    $router->get('/admin/content', [ContentController::class, 'index'], $admin, 'admin.content');
    $router->post('/admin/content/{block}', [ContentController::class, 'saveBlock'], $adminCsrf, 'admin.content.save');
    $router->post('/admin/faqs', [ContentController::class, 'saveFaq'], $adminCsrf, 'admin.faqs.save');
    $router->post('/admin/faqs/{id}/delete', [ContentController::class, 'deleteFaq'], $adminCsrf, 'admin.faqs.delete');

    // --- email --------------------------------------------------------------
    $router->get('/admin/email-templates', [EmailController::class, 'templates'], $admin, 'admin.email.templates');
    $router->get('/admin/email-templates/{id}', [EmailController::class, 'editTemplate'], $admin, 'admin.email.templates.edit');
    $router->post('/admin/email-templates/{id}', [EmailController::class, 'saveTemplate'], $adminCsrf, 'admin.email.templates.update');
    $router->post('/admin/email-templates/{id}/reset', [EmailController::class, 'resetTemplate'], $adminCsrf, 'admin.email.templates.reset');
    $router->get('/admin/email-queue', [EmailController::class, 'queue'], $admin, 'admin.email.queue');
    $router->post('/admin/email-queue/{id}/retry', [EmailController::class, 'retry'], $adminCsrf, 'admin.email.queue.retry');

    // --- settings -----------------------------------------------------------
    $router->get('/admin/settings', [SettingsController::class, 'index'], $admin, 'admin.settings');
    $router->post('/admin/settings/gateway/{code}', [SettingsController::class, 'saveGateway'], $superCsrf, 'admin.settings.gateway');
    $router->post('/admin/settings/smtp', [SettingsController::class, 'saveSmtp'], $superCsrf, 'admin.settings.smtp');
    $router->post('/admin/settings/smtp/test-connection', [SettingsController::class, 'testSmtp'], $superCsrf, 'admin.settings.smtp.test');
    $router->post('/admin/settings/smtp/send-test', [SettingsController::class, 'sendTestEmail'], $superCsrf, 'admin.settings.smtp.send');
    $router->post('/admin/settings/site', [SettingsController::class, 'saveSite'], $adminCsrf, 'admin.settings.site');
    $router->post('/admin/settings/gateway/{code}/test', [SettingsController::class, 'testGateway'], $superCsrf, 'admin.settings.gateway.test');

    // --- audit --------------------------------------------------------------
    $router->get('/admin/audit', [AuditController::class, 'index'], $admin, 'admin.audit');
};
