<?php
/**
 * Donation result / receipt page.
 *
 * The status shown is the status in our database, which is only ever changed
 * by a verified server-to-server response from the payment provider.
 *
 * @var array|null $donation
 * @var array $transactions
 * @var string|null $donorMessage
 * @var string|null $expected
 */

$currency = (string) ($donation['currency'] ?? 'PKR');

$presentation = [
    'completed'  => ['class' => 'alert-success', 'icon' => 'check', 'heading' => 'Thank you — your donation is confirmed', 'blurb' => 'Your payment was verified with the bank and your receipt is on its way to your inbox.'],
    'processing' => ['class' => 'alert-warning', 'icon' => 'clock', 'heading' => 'Your payment is being processed', 'blurb' => 'The bank has not finished confirming this payment yet. We will email you the moment it clears — no action is needed from you.'],
    'pending'    => ['class' => 'alert-warning', 'icon' => 'clock', 'heading' => 'Your payment is being processed', 'blurb' => 'We have recorded your donation and are waiting for the bank to confirm it.'],
    'failed'     => ['class' => 'alert-error', 'icon' => 'alert', 'heading' => 'That payment did not go through', 'blurb' => 'The payment was not completed, so no money has left your account. You can try again with the same or a different method.'],
    'cancelled'  => ['class' => 'alert-error', 'icon' => 'alert', 'heading' => 'That payment was cancelled', 'blurb' => 'The payment was cancelled before it completed, so no money has left your account.'],
    'refunded'   => ['class' => 'alert-info', 'icon' => 'info', 'heading' => 'This donation was refunded', 'blurb' => 'We have refunded this donation to your original payment method. It can take a few working days to appear on your statement.'],
    'abandoned'  => ['class' => 'alert-error', 'icon' => 'alert', 'heading' => 'That payment was not completed', 'blurb' => 'The payment session ended before it was completed. No money has left your account.'],
];

$status = (string) ($donation['status'] ?? '');
$view = $presentation[$status] ?? ['class' => 'alert-info', 'icon' => 'info', 'heading' => 'Donation status', 'blurb' => ''];

$icons = [
    'check' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>',
    'clock' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg>',
    'alert' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5h.01"/></svg>',
    'info'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 7.5h.01"/></svg>',
];
?>
<section class="section">
  <div class="wrap wrap-narrow">
    <?php if ($donation === null): ?>
      <div class="receipt-status">
        <div class="tick" style="background:#fdeceb;color:#a3231b">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
        </div>
        <h1>We could not find that donation</h1>
        <p><?= e((string) ($donorMessage ?? 'Check the link in your receipt email, or contact our team with the reference from your bank statement.')) ?></p>
        <div class="cta-actions">
          <a class="btn btn-brand" href="<?= e(base_url('fundraisers')) ?>">Browse fundraisers</a>
          <a class="btn btn-outline-brand" href="<?= e(base_url('support')) ?>">Contact support</a>
        </div>
      </div>
    <?php else: ?>
      <div class="receipt-status">
        <div class="tick <?= $status === 'completed' ? 'success-tick' : '' ?>" style="<?= $status === 'completed' ? '' : 'background:var(--brand-tint);color:var(--brand)' ?>">
          <?= $icons[$view['icon']] ?>
        </div>
        <h1><?= e($view['heading']) ?></h1>
        <p><?= e($view['blurb']) ?></p>
      </div>

      <?php if ($expected !== null && $expected !== $status): ?>
        <div class="alert alert-info">
          <?= $icons['info'] ?>
          <p>We checked this donation against our records rather than the page you landed on — this is its real, verified status.</p>
        </div>
      <?php endif; ?>

      <div class="panel">
        <div class="panel-head">
          <h2>Donation details</h2>
          <span class="<?= e(status_badge_class($status)) ?>"><?= e(str_replace('_', ' ', $status)) ?></span>
        </div>

        <dl class="kv">
          <dt>Reference</dt>
          <dd class="mono"><?= e((string) $donation['public_reference']) ?>
            <button class="btn btn-light btn-sm" type="button" data-copy="<?= e((string) $donation['public_reference']) ?>" style="margin-left:8px">Copy</button>
          </dd>
          <dt>Amount</dt>
          <dd><?= e(money((int) $donation['amount_minor'], $currency)) ?></dd>
          <dt>Date</dt>
          <dd><?= e(dt((string) $donation['created_at'], 'j F Y, H:i')) ?> UTC</dd>
          <?php if (!empty($donation['completed_at'])): ?>
            <dt>Confirmed</dt>
            <dd><?= e(dt((string) $donation['completed_at'], 'j F Y, H:i')) ?> UTC</dd>
          <?php endif; ?>
          <dt>Donor</dt>
          <dd><?= e(!empty($donation['anonymous']) ? 'Anonymous' : (string) ($donation['donor_name'] ?? '—')) ?></dd>
          <?php if (!empty($donation['fundraiser_title'])): ?>
            <dt>Supporting</dt>
            <dd><a href="<?= e(base_url('fundraisers/' . (string) $donation['fundraiser_slug'])) ?>"><?= e((string) $donation['fundraiser_title']) ?></a></dd>
          <?php endif; ?>
          <?php if (!empty($donation['campaign_title'])): ?>
            <dt>Campaign</dt>
            <dd><?= e((string) $donation['campaign_title']) ?></dd>
          <?php endif; ?>
          <?php if (!empty($donation['donor_message'])): ?>
            <dt>Your message</dt>
            <dd><?= e((string) $donation['donor_message']) ?></dd>
          <?php endif; ?>
        </dl>

        <?php if ($transactions !== []): ?>
          <h3 class="mt-3" style="font-size:16px">Payment record</h3>
          <div class="table-wrap">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Gateway</th>
                  <th>Environment</th>
                  <th>Status</th>
                  <th>Bank transaction</th>
                  <th>Recorded</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($transactions as $transaction): ?>
                  <tr>
                    <td><?= e(ucfirst((string) $transaction['gateway_code'])) ?></td>
                    <td><?= e(ucfirst((string) ($transaction['environment'] ?? ''))) ?></td>
                    <td><span class="<?= e(status_badge_class((string) $transaction['status'])) ?>"><?= e((string) $transaction['status']) ?></span></td>
                    <td class="mono"><?= e((string) ($transaction['provider_transaction_id'] ?: '—')) ?></td>
                    <td><?= e(dt((string) $transaction['created_at'], 'j M Y H:i')) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <div class="cta-actions">
        <?php if ($status === 'completed'): ?>
          <a class="btn btn-brand" href="<?= e(base_url('fundraisers')) ?>">Help another fundraiser</a>
          <?php if (can_fundraiser_capability('manage_pages')): ?>
            <a class="btn btn-outline-brand" href="<?= e(base_url('register')) ?>">Start your own</a>
          <?php endif; ?>
        <?php else: ?>
          <a class="btn btn-brand" href="<?= e(base_url('fundraisers')) ?>">Try again</a>
          <a class="btn btn-outline-brand" href="<?= e(base_url('support')) ?>">Get help with this payment</a>
        <?php endif; ?>
      </div>

      <p class="text-muted mt-3" style="font-size:13.5px;text-align:center">
        Keep your reference <span class="mono"><?= e((string) $donation['public_reference']) ?></span> — our team can trace your donation with it.
      </p>
    <?php endif; ?>
  </div>
</section>
