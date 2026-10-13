<?php
/**
 * Payment result card shown when the bank returns the donor to
 * fundraise-response.php (success / being confirmed / not completed).
 *
 * The outcome is never taken from the browser: it comes from the server-to-
 * server verification done in PaymentController::fundraiseResult().
 *
 * @var string     $outcome    completed|processing|failed
 * @var string     $reason     cancelled|expired|failed|... (failed only)
 * @var string     $reference  public donation reference
 * @var array|null $donation
 * @var string     $amountText
 * @var string     $retryUrl
 * @var string     $statusUrl
 * @var array      $org
 */

$icons = [
    'check' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>',
    'clock' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg>',
    'alert' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5h.01"/></svg>',
];

$bank = (array) ($org['bank'] ?? []);
$orgEmail = (string) ($org['email'] ?? '');
$orgPhone = (string) ($org['phone'] ?? '');
$targetText = (string) ($donation['fundraiser_title'] ?? 'Almarah Foundation general fund');
$isAnonymous = !empty($donation['anonymous']);
?>
<section class="section">
  <div class="wrap wrap-narrow">

    <?php if ($outcome === 'completed'): ?>
      <div class="receipt-status">
        <div class="tick success-tick"><?= $icons['check'] ?></div>
        <h1>JazakAllah Khair!</h1>
        <p>Your donation has been confirmed by the bank. May Allah accept your generosity. Ameen.</p>
      </div>
    <?php elseif ($outcome === 'processing'): ?>
      <div class="receipt-status">
        <div class="tick" style="background:var(--brand-tint);color:var(--brand)"><?= $icons['clock'] ?></div>
        <h1>Payment being confirmed</h1>
        <p>The bank has not given us a final answer yet, so your donation is not counted until it does. This usually settles within a few minutes &mdash; please do not pay again.</p>
      </div>
    <?php else: ?>
      <div class="receipt-status">
        <div class="tick" style="background:#fdeceb;color:#a3231b"><?= $icons['alert'] ?></div>
        <h1>Payment not completed</h1>
        <p>
          <?php if ($reason === 'cancelled'): ?>
            The payment was cancelled before it finished, so no money has been taken.
          <?php elseif ($reason === 'expired'): ?>
            This payment session expired before the bank could confirm it. If your bank shows a debit, contact us with your donation reference and we will trace it.
          <?php else: ?>
            The bank could not complete this payment, so your donation has not been counted. If your bank shows a debit, contact us with your donation reference and we will trace it.
          <?php endif; ?>
        </p>
      </div>
    <?php endif; ?>

    <?php if ($donation !== null): ?>
      <div class="panel">
        <div class="panel-head">
          <h2>Donation details</h2>
          <span class="<?= e(status_badge_class((string) $donation['status'])) ?>"><?= e(str_replace('_', ' ', (string) $donation['status'])) ?></span>
        </div>
        <dl class="kv">
          <dt>Reference</dt>
          <dd class="mono"><?= e($reference) ?>
            <button class="btn btn-light btn-sm" type="button" data-copy="<?= e($reference) ?>" style="margin-left:8px">Copy</button>
          </dd>
          <dt>Donor</dt>
          <dd><?= e($isAnonymous ? 'Anonymous' : (string) ($donation['donor_name'] ?? '—')) ?></dd>
          <dt>Giving to</dt>
          <dd><?= e($targetText) ?></dd>
          <dt><?= $outcome === 'completed' ? 'Amount paid' : 'Amount' ?></dt>
          <dd><?= e($amountText) ?></dd>
          <dt>Payment method</dt>
          <dd><?= e((string) ($donation['gateway_name'] ?? 'Card payment')) ?></dd>
          <?php if (!empty($donation['created_at'])): ?>
            <dt>Date</dt>
            <dd><?= e(dt((string) $donation['created_at'], 'j F Y, H:i')) ?> UTC</dd>
          <?php endif; ?>
        </dl>
        <?php if ($outcome === 'completed'): ?>
          <p class="muted" style="margin:16px 0 0;font-size:14px">
            A receipt has been emailed to <strong><?= e((string) ($donation['donor_email'] ?? 'your email address')) ?></strong>.
            Your gift now counts towards the fundraiser total.
          </p>
        <?php elseif ($outcome === 'processing'): ?>
          <p class="muted" style="margin:16px 0 0;font-size:14px">
            Keep your donation reference safe. You can check the latest status any time, and we will email you the moment the bank confirms.
          </p>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if ($outcome === 'failed' && $bank !== []): ?>
      <div class="panel" style="margin-top:20px">
        <div class="panel-head"><h2>Prefer a bank transfer?</h2></div>
        <p class="muted" style="margin:0 0 14px;font-size:14px">No money has been taken for this attempt. You can still give by transferring directly to our account:</p>
        <dl class="kv">
          <dt>Bank</dt><dd><?= e((string) ($bank['name'] ?? '')) ?></dd>
          <dt>Account title</dt><dd><?= e((string) ($bank['title'] ?? '')) ?></dd>
          <dt>Account no.</dt><dd class="mono"><?= e((string) ($bank['account'] ?? '')) ?></dd>
          <dt>IBAN</dt><dd class="mono"><?= e((string) ($bank['iban'] ?? '')) ?></dd>
          <dt>Branch</dt><dd><?= e((string) ($bank['branch'] ?? '')) ?></dd>
          <dt>SWIFT</dt><dd class="mono"><?= e((string) ($bank['swift'] ?? '')) ?></dd>
        </dl>
      </div>
    <?php endif; ?>

    <div class="panel" style="margin-top:20px;text-align:center">
      <h3 style="margin:0 0 6px;font-size:17px"><?= $outcome === 'failed' ? 'Need help?' : 'Questions? We are here to help.' ?></h3>
      <p class="muted" style="margin:0;font-size:14px">
        <?php if ($orgPhone !== ''): ?><strong><?= e($orgPhone) ?></strong><br><?php endif; ?>
        <?php if ($orgEmail !== ''): ?><a href="mailto:<?= e($orgEmail) ?>"><?= e($orgEmail) ?></a><?php endif; ?>
      </p>
    </div>

    <div class="cta-actions" style="margin-top:24px">
      <?php if ($outcome === 'failed'): ?>
        <a class="btn btn-brand" href="<?= e($retryUrl) ?>">Try again</a>
        <?php if ($reference !== ''): ?><a class="btn btn-outline-brand" href="<?= e($statusUrl) ?>">View donation status</a><?php endif; ?>
      <?php else: ?>
        <a class="btn btn-brand" href="<?= e($statusUrl) ?>"><?= $outcome === 'completed' ? 'View donation status' : 'Check donation status' ?></a>
        <a class="btn btn-outline-brand" href="<?= e(base_url('fundraisers')) ?>">All fundraisers</a>
      <?php endif; ?>
    </div>

  </div>
</section>
