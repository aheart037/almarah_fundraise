<?php
/**
 * Raw gateway callback log.
 *
 * @var array $callbacks
 */

$results = ['accepted' => 'badge-green', 'duplicate' => 'badge-purple', 'rejected' => 'badge-red', 'error' => 'badge-red', 'ignored' => 'badge-grey'];
?>
<div class="dash-head">
  <div>
    <h1>Payment callbacks</h1>
    <p class="sub">Every return trip from a payment provider, with the outcome of our server-side verification.</p>
  </div>
  <a class="btn btn-light" href="<?= e(base_url('admin/donations')) ?>">Back to donations</a>
</div>

<div class="alert alert-info">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 7.5h.01"/></svg>
  <p>
    Payloads are stored with credential-bearing and card fields removed before they are written, so this log is safe to inspect.
    A status change happens only after we ask the provider directly — never from what the browser sent.
  </p>
</div>

<section class="panel">
  <?php if ($callbacks === []): ?>
    <div class="empty-state">
      <h3>No callbacks recorded yet</h3>
      <p>Once a donor completes a payment, the return trip from the gateway will appear here.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr><th>When</th><th>Gateway</th><th>Method</th><th>Result</th><th>Transaction id</th><th>Processed</th><th class="wrap">Reason</th></tr>
        </thead>
        <tbody>
          <?php foreach ($callbacks as $callback): ?>
            <tr>
              <td><?= e(dt((string) $callback['created_at'], 'j M Y H:i')) ?></td>
              <td><?= e(ucfirst((string) ($callback['gateway_code'] ?? '—'))) ?></td>
              <td><?= e((string) $callback['callback_method']) ?></td>
              <td><span class="badge <?= e($results[(string) $callback['validation_result']] ?? 'badge-grey') ?>"><?= e((string) $callback['validation_result']) ?></span></td>
              <td class="mono"><?= e((string) ($callback['received_transaction_id'] ?: '—')) ?></td>
              <td><?= e(dt(isset($callback['processed_at']) ? (string) $callback['processed_at'] : null, 'j M Y H:i')) ?></td>
              <td class="wrap muted"><?= e((string) ($callback['reason'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="text-muted mt-2">Showing the most recent 80 callbacks.</p>
  <?php endif; ?>
</section>
