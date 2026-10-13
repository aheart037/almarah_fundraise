<?php

declare(strict_types=1);

/**
 * Almarah Foundation – Fundraise Payment Response Handler (gateway step 2).
 *
 * The fundraise counterpart of qurbani-response.php. The UBL / Etisalat EPG
 * sends the donor's browser back here after card authentication:
 *
 *     POST /fundraise-response.php?OrderID=<our order id>   TransactionID=<bank id>
 *
 * (This is the EPG ReturnPath registered by fundraise-submit.php.) Like the
 * Qurbani page, nothing here depends on the donor's session or a signed
 * token: the stored payment is found by OrderID / TransactionID.
 *
 * The payment is then verified SERVER-TO-SERVER with the gateway (EPG
 * Finalization) by PaymentController::fundraiseResult() — nothing the browser
 * claims is trusted — and the result card is rendered in the site's own
 * layout. Refreshing is safe: callback handling is idempotent.
 *
 * Meezan returns here by GET with its signed state, and the routed
 * /payments/etisalat/return callbacks stay registered for transactions that
 * were already in flight before this page became the ReturnPath.
 */

// This folder is the website (see index.php).
if (!defined('ALMARAH_WEB_ROOT')) {
    define('ALMARAH_WEB_ROOT', __DIR__);
}

use App\Core\Request;
use App\Core\Session;
use App\Http\Controllers\Public\PaymentController;

try {
    $app = require __DIR__ . '/bootstrap/app.php';
} catch (Throwable $bootFailure) {
    error_log('[almarah] fundraise-response boot failed: ' . $bootFailure->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'We could not load the payment result. If your bank shows a debit, please contact Almarah Foundation with your bank transaction reference.';
    exit;
}

// The gateway's POST is cross-site, so the donor's session cookie may not
// accompany it — nothing here depends on the session.
/** @var Session $session */
$session = $app->make(Session::class);
$session->start();

/** @var Request $request */
$request = $app->make(Request::class);

try {
    /** @var PaymentController $controller */
    $controller = $app->make(PaymentController::class);
    $controller->fundraiseResult($request)->send();
} catch (Throwable $e) {
    error_log('[almarah] fundraise-response failed: ' . $e->getMessage()
        . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'We could not load the payment result. If your bank shows a debit, please contact Almarah Foundation with your donation reference or bank transaction reference.';
}
exit;
