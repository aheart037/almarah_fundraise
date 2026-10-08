<?php
/**
 * Single donation with its payment transactions, callbacks and refunds.
 *
 * @var array $donation
 * @var array $transactions
 * @var array $callbacks   transaction id => rows
 * @var array $refunds     transaction id => rows
 */

$status = (string) $donation['status'];
$currency = (string) $donation['currency'];
$canRefund = $status === 'completed' && $transactions !== [];
?>
<div class="dash-head">
  <div>
    <h1><?= e((string) $donation['public_reference']) ?></h1>
    <p class="sub">
      <span class="<?= e(status_badge_class($status)) ?>"><?= e($status) ?></span>
      &middot; <?= e(money((int) $donation['amount_minor'], $currency)) ?>
      &middot; <?= e(dt((string) $donation['created_at'], 'j F Y, H:i')) ?> UTC
    </p>
  </div>
  <div class="sr-actions">
    <a class="btn btn-light" href="<?= e(base_url('admin/donations')) ?>">Back to donations</a>
    <?php if (!empty($donation['fundraiser_slug'])): ?>
      <a class="btn btn-outline-brand" href="<?= e(base_url('fundraisers/' . (string) $donation['fundraiser_slug'])) ?>" target="_blank" rel="noopener">Public page</a>
    <?php endif; ?>
  </div>
</div>

<div class="grid-2">
  <section class="panel">
    <h2>Donation</h2>
    <dl class="kv">
      <dt>Reference</dt><dd class="mono"><?= e((string) $donation['public_reference']) ?></dd>
      <dt>Amount</dt><dd><?= e(money((int) $donation['amount_minor'], $currency)) ?></dd>
      <dt>Status</dt><dd><span class="<?= e(status_badge_class($status)) ?>"><?= e($status) ?></span></dd>
      <dt>Created</dt><dd><?= e(dt((string) $donation['created_at'], 'j F Y H:i')) ?> UTC</dd>
      <dt>Confirmed</dt><dd><?= e(dt(isset($donation['completed_at']) ? (string) $donation['completed_at'] : null, 'j F Y H:i')) ?></dd>
      <dt>Donor</dt>
      <dd>
        <?= e(!empty($donation['anonymous']) ? 'Anonymous' : (string) ($donation['donor_name'] ?? '—')) ?>
        <div class="muted" style="font-size:12px"><?= e((string) ($donation['donor_email'] ?? '')) ?></div>
      </dd>
      <dt>Phone</dt><dd><?= e((string) ($donation['donor_phone'] ?? '—')) ?></dd>
      <?php if (!empty($donation['fundraiser_title'])): ?>
        <dt>Fundraiser</dt><dd><?= e((string) $donation['fundraiser_title']) ?></dd>
      <?php endif; ?>
      <?php if (!empty($donation['campaign_title'])): ?>
        <dt>Campaign</dt><dd><?= e((string) $donation['campaign_title']) ?></dd>
      <?php endif; ?>
      <dt>Anonymous</dt><dd><?= !empty($donation['anonymous']) ? 'Yes' : 'No' ?></dd>
      <dt>Receipt</dt><dd><?= e((string) ($donation['receipt_status'] ?? 'not sent')) ?></dd>
      <?php if (!empty($donation['donor_message'])): ?>
        <dt>Message</dt><dd><?= e((string) $donation['donor_message']) ?></dd>
      <?php endif; ?>
    </dl>
  </section>

  <section class="panel">
    <h2>Actions</h2>
    <p class="panel-sub">Both actions ask the payment provider directly. Nothing here can declare a payment successful on its own.</p>

    <?php foreach ($transactions as $transaction): ?>
      <?php $txnId = (int) $transaction['id']; ?>
      <div style="border:1px solid var(--line);border-radius:var(--radius);padding:16px;margin-bottom:14px">
        <div class="panel-head" style="margin-bottom:10px">
          <b>Transaction #<?= e((string) $txnId) ?></b>
          <span class="<?= e(status_badge_class((string) $transaction['status'])) ?>"><?= e((string) $transaction['status']) ?></span>
        </div>
        <dl class="kv" style="grid-template-columns:150px 1fr">
          <dt>Gateway</dt><dd><?= e((string) ($transaction['gateway_name'] ?? $transaction['gateway_code'] ?? '')) ?></dd>
          <dt>Environment</dt><dd><?= e(ucfirst((string) ($transaction['environment'] ?? ''))) ?></dd>
          <dt>Order id</dt><dd class="mono"><?= e((string) ($transaction['merchant_order_id'] ?? '—')) ?></dd>
          <dt>Provider txn</dt><dd class="mono"><?= e((string) ($transaction['provider_transaction_id'] ?? '—')) ?></dd>
          <dt>Provider code</dt><dd class="mono"><?= e((string) ($transaction['provider_response_code'] ?? '—')) ?></dd>
          <dt>Registered</dt><dd><?= e(dt(isset($transaction['registered_at']) ? (string) $transaction['registered_at'] : null, 'j M Y H:i')) ?></dd>
          <?php if (!empty($transaction['provider_response_description'])): ?>
            <dt>Provider said</dt><dd><?= e((string) $transaction['provider_response_description']) ?></dd>
          <?php endif; ?>
        </dl>

        <div class="form-actions">
          <?php if (in_array((string) $transaction['status'], ['pending', 'processing'], true)): ?>
            <form method="post" action="<?= e(base_url('admin/transactions/' . $txnId . '/reconcile')) ?>"
                  data-confirm="Ask the gateway for this transaction's real status?">
              <?= csrf_field() ?>
              <button class="btn btn-brand btn-sm" type="submit">Reconcile with gateway</button>
            </form>
          <?php endif; ?>

          <?php if ($canRefund): ?>
            <form method="post" action="<?= e(base_url('admin/donations/' . (string) $donation['id'] . '/refund')) ?>"
                  data-confirm="Send a refund request to the gateway for this donation?">
              <?= csrf_field() ?>
              <div class="field" style="margin:0">
                <label for="amount-<?= e((string) $txnId) ?>">Refund amount (blank = full)</label>
                <input class="input" type="number" id="amount-<?= e((string) $txnId) ?>" name="amount" step="1" min="1"
                       placeholder="<?= e(number_format(((int) $donation['amount_minor']) / 100, 0, '.', '')) ?>">
              </div>
              <div class="field" style="margin:0">
                <label for="reason-<?= e((string) $txnId) ?>">Reason<span class="req">*</span></label>
                <input class="input" type="text" id="reason-<?= e((string) $txnId) ?>" name="reason" required minlength="5" maxlength="500"
                       placeholder="Duplicate payment reported by the donor">
              </div>
              <button class="btn btn-outline-brand btn-sm" type="submit">Refund</button>
            </form>
          <?php endif; ?>
        </div>

        <?php $txnCallbacks = $callbacks[$txnId] ?? []; ?>
        <?php if ($txnCallbacks !== []): ?>
          <h4 class="mt-2" style="font-size:14px">Callbacks</h4>
          <div class="table-wrap">
            <table class="data-table">
              <thead><tr><th>When</th><th>Method</th><th>Result</th><th class="wrap">Reason</th></tr></thead>
              <tbody>
                <?php foreach ($txnCallbacks as $callback): ?>
                  <tr>
                    <td><?= e(dt((string) $callback['created_at'], 'j M Y H:i')) ?></td>
                    <td><?= e((string) $callback['callback_method']) ?></td>
                    <td><?= e((string) $callback['validation_result']) ?></td>
                    <td class="wrap muted"><?= e((string) ($callback['reason'] ?? '')) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>

        <?php $txnRefunds = $refunds[$txnId] ?? []; ?>
        <?php if ($txnRefunds !== []): ?>
          <h4 class="mt-2" style="font-size:14px">Refunds</h4>
          <div class="table-wrap">
            <table class="data-table">
              <thead><tr><th>When</th><th>Status</th><th class="num">Amount</th><th class="wrap">Provider response</th></tr></thead>
              <tbody>
                <?php foreach ($txnRefunds as $refund): ?>
                  <tr>
                    <td><?= e(dt((string) $refund['created_at'], 'j M Y H:i')) ?></td>
                    <td><span class="<?= e(status_badge_class((string) $refund['status'])) ?>"><?= e((string) $refund['status']) ?></span></td>
                    <td class="num"><?= e(money((int) $refund['amount_minor'], $currency)) ?></td>
                    <td class="wrap muted"><?= e((string) ($refund['provider_message'] ?? '')) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

    <?php if ($transactions === []): ?>
      <p class="text-muted">No payment transaction was created for this donation, so there is nothing to reconcile.</p>
    <?php endif; ?>
  </section>
</div>
