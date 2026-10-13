<?php

declare(strict_types=1);

/**
 * Almarah Foundation – Fundraise Donation Handler (payment gateway step 1).
 *
 * This is the fundraise counterpart of qurbani-submit.php on the Qurbani
 * website, with the same job and the same request/response contract:
 *
 *   1. the donation form (or any fetch() caller) POSTs the donor's details;
 *   2. the donation is validated and saved as "pending", then registered
 *      with the payment gateway (for UBL / Etisalat EPG that is the EPG
 *      Registration call — the gateway's ReturnPath is the canonical
 *      /payments/etisalat/return route, see EtisalatGateway::returnUrl());
 *   3. the caller is handed the gateway hand-off URL.
 *
 * Two response styles, exactly like the Qurbani page:
 *
 *   - AJAX / fetch callers get JSON:
 *       {"success":true,"payment_page":"<hand-off url>","redirect_url":"<same>",
 *        "reference":"ALM-2026-…","gateway":"etisalat"}
 *     or {"success":false,"message":"…"} on any failure. Send the donor's
 *     browser to payment_page; for Etisalat that URL is the platform's own
 *     bridge page, which auto-POSTs the stored TransactionID to the bank's
 *     PaymentPage — the TransactionID itself is never exposed to the
 *     browser, unlike the original Qurbani hand-off.
 *
 *   - A classic form POST (what the site's own donate form sends) is
 *     answered with a 302 redirect straight to the same hand-off URL.
 *
 * Everything after validation is the platform's own built-in machinery:
 * DonationService creates the donor/donation/transaction rows and the
 * configured gateway class performs Registration. This file holds no
 * merchant credentials of its own — they stay in Admin → Settings (or the
 * ETISALAT_* environment values), shared with the /donate flow, whose POST
 * route remains available as an alias for this same step.
 *
 * The bank returns through /payments/etisalat/return (step 2).
 */

// This folder is the website (see index.php).
if (!defined('ALMARAH_WEB_ROOT')) {
    define('ALMARAH_WEB_ROOT', __DIR__);
}

use App\Core\Csrf;
use App\Core\Money;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Payments\Exceptions\PaymentGatewayException;
use App\Payments\PaymentGatewayManager;
use App\Services\DonationService;

/** Send a Qurbani-style JSON answer and stop. */
$jsonOut = static function (bool $ok, array $extra = [], int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode(['success' => $ok] + $extra, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{"success":false}';
    exit;
};

try {
    $app = require __DIR__ . '/bootstrap/app.php';
} catch (Throwable $bootFailure) {
    error_log('[almarah] fundraise-submit boot failed: ' . $bootFailure->getMessage());
    $jsonOut(false, ['message' => 'Online payments are unavailable right now. Please try again later or contact our team.'], 500);
}

/** @var Session $session */
$session = $app->make(Session::class);
$session->start();

/** @var Request $request */
$request = $app->make(Request::class);

$isAjax = $request->isAjax()
    || str_contains(strtolower((string) $request->header('accept', '')), 'application/json');

/** Classic-form failure: keep the donor's input, tell them why, go back. */
$failBack = static function (string $message, string $target, string $kind) use ($session, $request, $isAjax, $jsonOut): void {
    if ($isAjax) {
        $jsonOut(false, ['message' => $message]);
    }
    $old = $request->all();
    unset($old['_token'], $old['password'], $old['password_confirmation']);
    $session->flash('_old', $old);
    $session->flash('error', $message);
    $back = '/donate/' . rawurlencode($target !== '' ? $target : 'general')
        . ($kind !== '' && $kind !== 'fundraiser' ? '?type=' . rawurlencode($kind) : '');
    header('Location: ' . \App\Core\Response::locationFor($back), true, 302);
    exit;
};

if (!$request->isPost()) {
    if ($isAjax) {
        $jsonOut(false, ['message' => 'Invalid request.'], 405);
    }
    header('Location: ' . \App\Core\Response::locationFor('/fundraisers'), true, 302);
    exit;
}

// The site's own form carries a CSRF token; require it for browser posts.
// (The gateway's return trip uses its callback route and never passes
// through here, so this cannot lock a donor out after paying.)
/** @var Csrf $csrf */
$csrf = $app->make(Csrf::class);
if (!$csrf->check($request)) {
    if ($isAjax) {
        $jsonOut(false, ['message' => 'Your session expired. Please reload the donation form and try again.'], 419);
    }
    $session->flash('error', 'Your session expired. Please try again.');
    header('Location: ' . \App\Core\Response::locationFor('/fundraisers'), true, 302);
    exit;
}

$target = trim((string) $request->input('target', 'general'));
$kind = trim((string) $request->input('type', ''));

/** @var DonationService $donations */
$donations = $app->make(DonationService::class);
/** @var PaymentGatewayManager $gateways */
$gateways = $app->make(PaymentGatewayManager::class);

// --- resolve what the donation belongs to (same rules as /donate) ---------
if ($target === '' || $target === 'general' || $target === 'foundation') {
    $resolved = ['kind' => 'general', 'record' => ['title' => 'Almarah Foundation general fund']];
} else {
    $resolved = $donations->resolveTarget($target, $kind !== '' ? $kind : null);
    if ($resolved !== null
        && $resolved['kind'] === 'fundraiser'
        && (string) ($resolved['record']['status'] ?? '') !== 'published') {
        $resolved = null; // only live pages may accept money
    }
}

if ($resolved === null) {
    $failBack('We could not find that fundraiser or campaign.', $target, $kind);
}

// --- validate the donor input (same rules as DonationController) ----------
$amountRaw = $request->input('amount');
if ($amountRaw === null || $amountRaw === '') {
    $amountRaw = $request->input('total_amount'); // Qurbani-style field name
}

$gatewayCode = trim((string) $request->input('gateway', ''));
if ($gatewayCode === '') {
    $choices = $gateways->choices();
    $gatewayCode = (string) ($choices[0]['code'] ?? '');
}

$validator = Validator::make($request->all() + ['gateway' => $gatewayCode], [
    'gateway'   => 'required|string',
    'name'      => 'required|string|min:3|max:120',
    'email'     => 'required|email|max:190',
    'phone'     => 'nullable|string|max:30',
    'message'   => 'nullable|string|max:500',
    'anonymous' => 'nullable|in:1,on,yes',
    'cover_fees'=> 'nullable|in:1,on,yes',
]);

if ($validator->fails()) {
    $failBack((string) ($validator->firstError() ?? 'Please check the form and try again.'), $target, $kind);
}

if (!is_string($amountRaw) && !is_int($amountRaw) && !is_float($amountRaw)) {
    $failBack('Enter a valid donation amount in rupees.', $target, $kind);
}

try {
    $amountMinor = Money::fromProviderString((string) $amountRaw);
} catch (InvalidArgumentException) {
    $failBack('Enter a valid donation amount in rupees, using no more than two decimal places.', $target, $kind);
}
if ($amountMinor <= 0) {
    $failBack('Enter a valid donation amount in rupees.', $target, $kind);
}

if (!in_array($gatewayCode, $gateways->availableCodes(), true)) {
    $failBack('Choose a valid payment method.', $target, $kind);
}

$user = $app->make(\App\Services\AuthService::class)->user();

try {
    // Creates the pending donation and runs gateway Registration. For
    // Etisalat Registration uses /payments/etisalat/return for its callback;
    // the returned redirect_url is the bridge page that hands the donor over
    // to the bank's PaymentPage. For Meezan it is the hosted formUrl.
    $result = $donations->initiate([
        'amount_minor' => $amountMinor,
        'name'         => (string) $request->input('name'),
        'email'        => (string) $request->input('email'),
        'phone'        => $request->input('phone'),
        'message'      => $request->input('message'),
        'anonymous'    => (bool) $request->input('anonymous'),
        'cover_fees'   => (bool) $request->input('cover_fees'),
        'ip'           => $request->ip(),
        'user_id'      => $user['id'] ?? null,
    ], $resolved, $gatewayCode);
} catch (PaymentGatewayException | InvalidArgumentException $e) {
    $failBack($e->getMessage(), $target, $kind);
} catch (Throwable $e) {
    error_log('[almarah] fundraise-submit failed: ' . $e->getMessage()
        . ' in ' . $e->getFile() . ':' . $e->getLine());
    $failBack('We could not start the payment just now. Please try again, or contact our team to give another way.', $target, $kind);
}

if ($isAjax) {
    $jsonOut(true, [
        'message'      => 'Registered with the payment gateway.',
        'reference'    => $result['reference'],
        'gateway'      => $result['gateway'],
        // Qurbani contract key + the platform's own name for the same URL.
        'payment_page' => $result['redirect_url'],
        'redirect_url' => $result['redirect_url'],
    ]);
}

header('Location: ' . $result['redirect_url'], true, 302);
exit;
