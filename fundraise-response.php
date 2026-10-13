<?php

declare(strict_types=1);

/**
 * Almarah Foundation – Fundraise Payment Response Handler (gateway step 2).
 *
 * This is the fundraise counterpart of qurbani-response.php on the Qurbani
 * website: the payment gateway sends the donor's browser back here after
 * card authentication, and this page settles the payment and shows the
 * result.
 *
 *   - UBL / Etisalat EPG POSTs its TransactionID here (this URL is the
 *     ReturnPath sent with every Registration — see
 *     EtisalatGateway::returnUrl()). Meezan returns here by GET with the
 *     signed state issued at registration.
 *   - The payment is then verified SERVER-TO-SERVER with the gateway
 *     (EPG Finalization / Meezan getOrderStatus) through the platform's
 *     PaymentController + DonationService. Nothing the browser claims —
 *     no response code, no amount — is ever trusted or even displayed as
 *     proof; only the gateway's own answer can complete a donation.
 *   - Finally this page renders the Qurbani-style result card (success /
 *     processing / not completed) with the donation reference, amount and
 *     what to do next. Refreshing is safe: callback handling is
 *     idempotent, so a reload simply re-reads the stored outcome.
 *
 * The older routes /payments/etisalat/return and /payments/meezan/return
 * remain registered and run the identical verification for any transaction
 * registered before this page existed; both end on the /donation status
 * pages, while donors who come through fundraise-submit.php land here.
 */

// This folder is the website (see index.php).
if (!defined('ALMARAH_WEB_ROOT')) {
    define('ALMARAH_WEB_ROOT', __DIR__);
}

use App\Core\Money;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Http\Controllers\Public\PaymentController;
use App\Repositories\DonationRepository;
use App\Repositories\PaymentRepository;

$esc = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

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
// accompany it — nothing here depends on the session. Start it only so the
// container's request lifecycle matches the rest of the site.
/** @var Session $session */
$session = $app->make(Session::class);
$session->start();

/** @var Request $request */
$request = $app->make(Request::class);
$payload = $request->all();

// --- which gateway is calling back? ---------------------------------------
// Prefer evidence from our own stored transaction over any claim in the
// request: the signed state (Meezan) or the TransactionID (EPG) both
// resolve to the row created at Registration, and that row names its
// gateway. Default to Etisalat, whose POST is the common case here.
/** @var PaymentRepository $payments */
$payments = $app->make(PaymentRepository::class);
$gateway = 'etisalat';

$stateValue = $payload['state'] ?? $payload['token'] ?? '';
$stateToken = is_scalar($stateValue) ? trim((string) $stateValue) : '';
$transactionIdValue = $payload['TransactionID'] ?? $payload['transactionId'] ?? '';
$transactionId = is_scalar($transactionIdValue) ? trim((string) $transactionIdValue) : '';

if ($transactionId !== '') {
    $gateway = 'etisalat';
} elseif ($stateToken !== '') {
    $stored = $payments->findByCallbackState(hash('sha256', $stateToken));
    if ($stored !== null && (string) ($stored['gateway_code'] ?? '') !== '') {
        $gateway = strtolower((string) $stored['gateway_code']);
    }
} else {
    foreach (['merchant_order_id', 'orderNumber', 'OrderID', 'orderId'] as $key) {
        $value = $payload[$key] ?? '';
        if (!is_scalar($value) || trim((string) $value) === '') {
            continue;
        }
        $stored = $payments->findByMerchantOrderId(trim((string) $value))
            ?? $payments->findByProviderOrderId('etisalat', trim((string) $value))
            ?? $payments->findByProviderOrderId('meezan', trim((string) $value));
        if ($stored !== null && (string) ($stored['gateway_code'] ?? '') !== '') {
            $gateway = strtolower((string) $stored['gateway_code']);
            break;
        }
    }
}

// --- verify with the gateway (Finalization happens inside) -----------------
/** @var PaymentController $controller */
$controller = $app->make(PaymentController::class);
try {
    $result = $gateway === 'meezan'
        ? $controller->meezanReturn($request)
        : $controller->etisalatReturn($request);
} catch (Throwable $e) {
    error_log('[almarah] fundraise-response verification failed: ' . $e->getMessage()
        . ' in ' . $e->getFile() . ':' . $e->getLine());
    $result = Response::redirect('/donation/failed?reason=unknown');
}

// PaymentController answers with a redirect to the canonical status page;
// translate that outcome into this page's Qurbani-style result card.
$location = (string) ($result->headers()['Location'] ?? '');
$query = [];
parse_str((string) (parse_url($location, PHP_URL_QUERY) ?? ''), $query);
$reference = isset($query['ref']) && is_scalar($query['ref']) ? trim((string) $query['ref']) : '';
$reason = isset($query['reason']) && is_scalar($query['reason']) ? trim((string) $query['reason']) : '';

$outcome = 'failed';
if (str_contains($location, '/donation/success')) {
    $outcome = 'completed';
} elseif (str_contains($location, '/donation/processing')) {
    $outcome = 'processing';
}

/** @var DonationRepository $donationRecords */
$donationRecords = $app->make(DonationRepository::class);
$donation = $reference !== '' ? $donationRecords->findByReference($reference) : null;

$org = (array) config('app.org', []);
$orgEmail = (string) ($org['email'] ?? 'info@almarah.org');
$orgPhone = (string) ($org['phone'] ?? '');

$statusUrl = $reference !== '' ? Response::locationFor('/donation/' . rawurlencode($reference)) : Response::locationFor('/fundraisers');
$retryUrl = Response::locationFor('/fundraisers');
if ($donation !== null && !empty($donation['fundraiser_slug'])) {
    $retryUrl = Response::locationFor('/donate/' . rawurlencode((string) $donation['fundraiser_slug']));
}

$amountText = $donation !== null
    ? Money::format((int) $donation['amount_minor'], (string) ($donation['currency'] ?? 'PKR'))
    : '';
$targetText = (string) ($donation['fundraiser_title'] ?? 'Almarah Foundation general fund');
$dateText = '';
if ($donation !== null && !empty($donation['created_at'])) {
    $ts = strtotime((string) $donation['created_at'] . ' UTC');
    $dateText = $ts === false ? '' : gmdate('d M Y, h:i A', $ts) . ' (UTC)';
}

http_response_code(200);
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: SAMEORIGIN');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title><?= $outcome === 'completed' ? 'Payment Successful' : ($outcome === 'processing' ? 'Payment Processing' : 'Payment Not Completed') ?> – Almarah Foundation</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Poppins',sans-serif;background:#fceef4;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}
.card{background:#fff;border-radius:24px;padding:48px 40px;max-width:560px;width:100%;text-align:center;box-shadow:0 8px 48px rgba(175,47,103,.15);}
.card.success{border-top:5px solid #2ecc71;}
.card.processing{border-top:5px solid #f0ad4e;}
.card.failed{border-top:5px solid #e74c3c;}
.icon{font-size:4rem;margin-bottom:16px;}
h1{font-size:1.65rem;color:#8a2050;margin-bottom:10px;}
.sub{color:#7a4560;font-size:.95rem;line-height:1.7;margin-bottom:18px;}
.ref-box{background:#fceef4;border-radius:12px;padding:14px 20px;margin:18px 0;font-weight:800;color:#af2f67;font-size:1rem;letter-spacing:.03em;word-break:break-all;}
.details{text-align:left;border:1.5px solid #f0e0e8;border-radius:14px;overflow:hidden;margin:18px 0;}
.detail-row{display:flex;justify-content:space-between;align-items:center;padding:11px 18px;font-size:.87rem;border-bottom:1px solid #f8eef4;}
.detail-row:last-child{border:none;}
.detail-row .lbl{color:#7a4560;}
.detail-row .val{color:#8a2050;font-weight:700;text-align:right;max-width:60%;word-break:break-word;}
.note{font-size:.82rem;color:#7a4560;margin-top:14px;line-height:1.6;}
.contact{background:#8a2050;border-radius:16px;padding:18px 22px;color:#fff;margin-top:22px;font-size:.87rem;line-height:1.9;}
.contact .phone{font-size:1.05rem;font-weight:800;color:#ffda00;display:block;margin:4px 0;}
.btn{display:inline-block;margin-top:22px;background:#af2f67;color:#fff;border-radius:50px;padding:13px 34px;font-weight:800;font-size:.95rem;text-decoration:none;transition:.2s;border:none;cursor:pointer;}
.btn:hover{background:#8a2050;}
.btn-out{background:transparent;color:#af2f67;border:2px solid #af2f67;margin-left:10px;}
.btn-out:hover{background:#fceef4;}
</style>
</head>
<body>
<?php if ($outcome === 'completed'): ?>
<div class="card success">
  <div class="icon">🎉</div>
  <h1>JazakAllah Khair!</h1>
  <p class="sub">Your donation has been confirmed by the bank. May Allah accept your generosity. <em>Ameen.</em></p>
  <?php if ($reference !== ''): ?><div class="ref-box">Donation Ref: <?= $esc($reference) ?></div><?php endif; ?>
  <?php if ($donation !== null): ?>
  <div class="details">
    <div class="detail-row"><span class="lbl">Donor Name</span><span class="val"><?= $esc($donation['donor_name'] ?? 'Anonymous') ?></span></div>
    <div class="detail-row"><span class="lbl">Giving To</span><span class="val"><?= $esc($targetText) ?></span></div>
    <div class="detail-row"><span class="lbl">Amount Paid</span><span class="val"><?= $esc($amountText) ?></span></div>
    <div class="detail-row"><span class="lbl">Payment Method</span><span class="val"><?= $esc($donation['gateway_name'] ?? 'Card payment') ?></span></div>
    <?php if ($dateText !== ''): ?><div class="detail-row"><span class="lbl">Date</span><span class="val"><?= $esc($dateText) ?></span></div><?php endif; ?>
  </div>
  <p class="note">A receipt has been emailed to <strong><?= $esc($donation['donor_email'] ?? 'your email address') ?></strong>. Your gift now counts towards the fundraiser total.</p>
  <?php endif; ?>
  <div class="contact">
    Questions? We're here to help.<br/>
    <?php if ($orgPhone !== ''): ?><span class="phone">📞 <?= $esc($orgPhone) ?></span><?php endif; ?>
    ✉️ <?= $esc($orgEmail) ?>
  </div>
  <a class="btn" href="<?= $esc($statusUrl) ?>">View Donation Status</a>
  <a class="btn btn-out" href="<?= $esc(Response::locationFor('/fundraisers')) ?>">← All Fundraisers</a>
</div>
<?php elseif ($outcome === 'processing'): ?>
<div class="card processing">
  <div class="icon">⏳</div>
  <h1>Payment Being Confirmed</h1>
  <p class="sub">The bank has not given us a final answer yet, so your donation is not counted until it does. This usually settles within a few minutes — please do not pay again.</p>
  <?php if ($reference !== ''): ?><div class="ref-box">Donation Ref: <?= $esc($reference) ?></div><?php endif; ?>
  <?php if ($donation !== null): ?>
  <div class="details">
    <div class="detail-row"><span class="lbl">Giving To</span><span class="val"><?= $esc($targetText) ?></span></div>
    <div class="detail-row"><span class="lbl">Amount</span><span class="val"><?= $esc($amountText) ?></span></div>
    <div class="detail-row"><span class="lbl">Status</span><span class="val">Confirming with the bank</span></div>
  </div>
  <?php endif; ?>
  <p class="note">Keep your donation reference safe. You can check the latest status any time, and we will email you the moment the bank confirms.</p>
  <div class="contact">
    Need help?<br/>
    <?php if ($orgPhone !== ''): ?><span class="phone">📞 <?= $esc($orgPhone) ?></span><?php endif; ?>
    ✉️ <?= $esc($orgEmail) ?>
  </div>
  <a class="btn" href="<?= $esc($statusUrl) ?>">Check Donation Status</a>
  <a class="btn btn-out" href="<?= $esc(Response::locationFor('/fundraisers')) ?>">← All Fundraisers</a>
</div>
<?php else: ?>
<div class="card failed">
  <div class="icon">⚠️</div>
  <h1>Payment Not Completed</h1>
  <p class="sub">
    <?php if ($reason === 'cancelled'): ?>
      The payment was cancelled before it finished, so no money has been taken.
    <?php elseif ($reason === 'expired'): ?>
      This payment session expired before the bank could confirm it. If your bank shows a debit, contact us with your donation reference and we will trace it.
    <?php else: ?>
      The bank could not complete this payment, so your donation has not been counted. If your bank shows a debit, contact us with your donation reference and we will trace it.
    <?php endif; ?>
  </p>
  <?php if ($reference !== ''): ?><div class="ref-box">Donation Ref: <?= $esc($reference) ?></div><?php endif; ?>
  <?php if ($donation !== null): ?>
  <div class="details">
    <div class="detail-row"><span class="lbl">Giving To</span><span class="val"><?= $esc($targetText) ?></span></div>
    <div class="detail-row"><span class="lbl">Amount</span><span class="val"><?= $esc($amountText) ?></span></div>
    <div class="detail-row"><span class="lbl">Status</span><span class="val">Not completed</span></div>
  </div>
  <?php endif; ?>
  <p class="note">You can try the online payment again, or contact our team to give another way. Please keep your donation reference if you have one.</p>
  <div class="contact">
    <strong style="color:#fff;">Contact us for assistance:</strong><br/>
    <?php if ($orgPhone !== ''): ?><span class="phone">📞 <?= $esc($orgPhone) ?></span><?php endif; ?>
    ✉️ <?= $esc($orgEmail) ?>
  </div>
  <a class="btn" href="<?= $esc($retryUrl) ?>">🔄 Try Again</a>
  <?php if ($reference !== ''): ?><a class="btn btn-out" href="<?= $esc($statusUrl) ?>">View Donation Status</a><?php endif; ?>
</div>
<?php endif; ?>
</body>
</html>
